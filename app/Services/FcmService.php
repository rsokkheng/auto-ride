<?php

namespace App\Services;

use App\Models\DriverDevice;
use App\Models\PushNotification;
use App\Models\User;
use App\Support\LocalizedText;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging v1 API (HTTP).
 *
 * Uses the same service-account JSON file as FirestoreService.
 * Scope: https://www.googleapis.com/auth/firebase.messaging
 */
class FcmService
{
    private const SCOPES = ['https://www.googleapis.com/auth/firebase.messaging'];

    // FCM error codes that mean the token is permanently dead
    private const DEAD_TOKEN_ERRORS = ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'];

    /**
     * Notification type → (sound name, Android channel). Single source of truth
     * so per-event sound/channel choices can't silently drift between call sites
     * (rideRequested() previously lost its 'sound' key in a refactor because it
     * was set ad hoc in each caller's $data array instead of here).
     */
    private const TYPE_SOUNDS = [
        'ride_requested'     => ['booking',  'booking_alerts'],
        'delivery_requested' => ['booking',  'booking_alerts'],
        'ride_accepted'      => ['accepted', 'ride_updates'],
        'delivery_accepted'  => ['accepted', 'ride_updates'],
    ];

    private ?string $accessToken = null;
    private int     $tokenExpiry = 0;
    private ?string $projectId   = null;

    // ── Public send helpers ───────────────────────────────────────────────────
    //
    // $title / $body may be a plain string or a LocalizedText (en / kh / zh).
    // Each device gets the text in its user's saved language; the in-app
    // notification row keeps all three so the list follows the app language.

    /**
     * Send to a single user via their users.fcm_token (fallback / passenger use).
     */
    public function sendToUser(User $user, string|LocalizedText $title, string|LocalizedText $body, array $data = []): void
    {
        if (! $user->fcm_token) return;
        [$title, $body] = [LocalizedText::wrap($title), LocalizedText::wrap($body)];

        $fcmStatus = $this->sendOne($user->fcm_token, $title->for($user), $body->for($user), $data);
        $this->logNotification($user, $title, $body, $data, $user->fcm_token, $fcmStatus);

        if ($this->isDeadToken($fcmStatus)) {
            $user->update(['fcm_token' => null]);
        }
    }

    /**
     * Send to a driver via all their registered devices in driver_devices.
     * Falls back to users.fcm_token if no devices are registered.
     */
    public function sendToDriver(User $driver, string|LocalizedText $title, string|LocalizedText $body, array $data = []): void
    {
        $devices = DriverDevice::where('user_id', $driver->id)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            // Fallback to legacy single token
            $this->sendToUser($driver, $title, $body, $data);
            return;
        }

        [$title, $body] = [LocalizedText::wrap($title), LocalizedText::wrap($body)];

        foreach ($devices as $device) {
            $fcmStatus = $this->sendOne($device->token, $title->for($driver), $body->for($driver), $data, $device->platform);
            $this->logNotification($driver, $title, $body, $data, $device->token, $fcmStatus);

            if ($this->isDeadToken($fcmStatus)) {
                $device->update(['is_active' => false]);
                if ($driver->fcm_token === $device->token) {
                    $driver->update(['fcm_token' => null]);
                }
                Log::error('[FCM] Dead token deactivated', [
                    'driver_id' => $driver->id,
                    'device_id' => $device->id,
                    'platform'  => $device->platform,
                    'token'     => substr($device->token, 0, 20) . '...',
                    'fcm_error' => $fcmStatus,
                ]);
            } else {
                $device->update(['last_used_at' => now()]);
                Log::info('[FCM] Push sent', [
                    'type'       => $data['type'] ?? 'unknown',
                    'booking_id' => $data['booking_id'] ?? ($data['ride_id'] ?? null),
                    'driver_id'  => $driver->id,
                    'device_id'  => $device->id,
                    'platform'   => $device->platform,
                    'status'     => $fcmStatus,
                ]);
            }
        }
    }

    /**
     * Push to a user on whichever devices they have, and always keep a row in
     * their in-app notification list — even when no device is registered.
     */
    public function notifyUser(User $user, string|LocalizedText $title, string|LocalizedText $body, array $data = []): void
    {
        $hasDevice = $user->fcm_token
            || ($user->role === 'driver' && DriverDevice::where('user_id', $user->id)->where('is_active', true)->exists());

        if (! $hasDevice) {
            $this->logNotification($user, LocalizedText::wrap($title), LocalizedText::wrap($body), $data, '', 'NO_TOKEN');
            return;
        }

        $user->role === 'driver'
            ? $this->sendToDriver($user, $title, $body, $data)
            : $this->sendToUser($user, $title, $body, $data);
    }

    /**
     * Push to a user's phone without adding to their in-app notification list —
     * for alerts that already have their own inbox (admin alerts).
     */
    public function pushOnly(User $user, string|LocalizedText $title, string|LocalizedText $body, array $data = []): void
    {
        if (! $user->fcm_token) return;

        $fcmStatus = $this->sendOne($user->fcm_token, LocalizedText::wrap($title)->for($user), LocalizedText::wrap($body)->for($user), $data);
        if ($this->isDeadToken($fcmStatus)) {
            $user->update(['fcm_token' => null]);
        }
    }

    /**
     * Push to a bare device token with no account behind it (guest shoppers),
     * in the language of the current request.
     */
    public function sendToToken(string $fcmToken, string|LocalizedText $title, string|LocalizedText $body, array $data = []): string
    {
        return $this->sendOne($fcmToken, LocalizedText::wrap($title)->in(), LocalizedText::wrap($body)->in(), $data);
    }

    /**
     * Send to multiple users at once (passengers / broadcast).
     */
    public function sendToUsers(array $users, string|LocalizedText $title, string|LocalizedText $body, array $data = []): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $data);
        }
    }

    /**
     * Push a promo/event announcement to one user (drivers get it on all their
     * registered devices, others via their single fcm_token).
     */
    public function promoEvent(User $user, int $eventId, string|LocalizedText $title, string|LocalizedText $body, ?string $imageUrl = null): void
    {
        $data = [
            'type'     => 'promo_event',
            'event_id' => (string) $eventId,
        ];
        if ($imageUrl) {
            $data['image_url'] = $imageUrl;
        }

        $user->role === 'driver'
            ? $this->sendToDriver($user, $title, $body, $data)
            : $this->sendToUser($user, $title, $body, $data);
    }

    /** title + body for a lang/{locale}/push.php entry, in all three languages. */
    public static function message(string $key, array $replace = [], string $bodyKey = 'body'): array
    {
        return [
            LocalizedText::trans("push.{$key}.title", $replace),
            LocalizedText::trans("push.{$key}.{$bodyKey}", $replace),
        ];
    }

    /** Driver display name, or "Your driver" in each language. */
    private static function driverName(?string $name): LocalizedText
    {
        return filled($name) ? LocalizedText::plain($name) : LocalizedText::trans('push.your_driver');
    }

    // ── Ride notification helpers ─────────────────────────────────────────────

    public function rideRequested(User $driver, int $rideId, string $pickup, string $dropoff, string $passengerName = '', int $fare = 0): void
    {
        $this->sendToDriver($driver,
            ...self::message('ride_requested'),
            data: [
                'type'           => 'ride_requested',
                'booking_id'     => (string) $rideId,
                'passenger_name' => $passengerName,
                'fare'           => (string) $fare,
                'pickup'         => $pickup,
                'dropoff'        => $dropoff,
            ]
        );
    }

    public function rideRequestedToMany(array $drivers, int $rideId, string $pickup, string $dropoff, string $passengerName = '', int $fare = 0): void
    {
        foreach ($drivers as $driver) {
            $this->rideRequested($driver, $rideId, $pickup, $dropoff, $passengerName, $fare);
        }
    }

    public function rideAccepted(User $passenger, int $rideId, ?string $driverName): void
    {
        $this->sendToUser($passenger,
            ...self::message('ride_accepted', ['driver' => self::driverName($driverName)]),
            data: ['type' => 'ride_accepted', 'booking_id' => (string) $rideId]
        );
    }

    public function rideRejected(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            ...self::message('ride_rejected'),
            data: ['type' => 'ride_rejected', 'booking_id' => (string) $rideId]
        );
    }

    public function driverArrived(User $passenger, int $rideId, ?string $driverName): void
    {
        $this->sendToUser($passenger,
            ...self::message('driver_arrived', ['driver' => self::driverName($driverName)]),
            data: ['type' => 'driver_arrived', 'booking_id' => (string) $rideId]
        );
    }

    public function rideStarted(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            ...self::message('ride_started'),
            data: ['type' => 'ride_started', 'booking_id' => (string) $rideId]
        );
    }

    public function rideCompleted(User $passenger, int $rideId, int $fare): void
    {
        $this->sendToUser($passenger,
            ...self::message('ride_completed', ['fare' => $fare]),
            data: ['type' => 'ride_completed', 'booking_id' => (string) $rideId, 'fare' => (string) $fare]
        );
    }

    public function rideCancelledByDriver(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            ...self::message('ride_cancelled_driver'),
            data: ['type' => 'ride_cancelled', 'booking_id' => (string) $rideId, 'cancelled_by' => 'driver']
        );
    }

    public function rideCancelledByPassenger(User $driver, int $rideId): void
    {
        $this->sendToDriver($driver,
            ...self::message('ride_cancelled_passenger'),
            data: ['type' => 'ride_cancelled', 'booking_id' => (string) $rideId, 'cancelled_by' => 'passenger']
        );
    }

    // ── Delivery notification helpers ─────────────────────────────────────────

    public function deliveryRequested(User $driver, int $deliveryId, string $pickup, string $dropoff): void
    {
        $this->sendToDriver($driver,
            ...self::message('delivery_requested', ['pickup' => $pickup, 'dropoff' => $dropoff]),
            data: ['type' => 'delivery_requested', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryAccepted(User $sender, int $deliveryId, ?string $driverName): void
    {
        $this->sendToUser($sender,
            ...self::message('delivery_accepted', ['driver' => self::driverName($driverName)]),
            data: ['type' => 'delivery_accepted', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryPickedUp(User $sender, int $deliveryId, ?string $driverName): void
    {
        $this->sendToUser($sender,
            ...self::message('delivery_picked_up', ['driver' => self::driverName($driverName)]),
            data: ['type' => 'delivery_picked_up', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryCompleted(User $sender, int $deliveryId): void
    {
        $this->sendToUser($sender,
            ...self::message('delivery_completed'),
            data: ['type' => 'delivery_completed', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryCancelled(User $user, int $deliveryId, string $cancelledBy): void
    {
        $this->sendToUser($user,
            ...self::message('delivery_cancelled', [], $cancelledBy === 'driver' ? 'body_by_driver' : 'body'),
            data: ['type' => 'delivery_cancelled', 'delivery_id' => (string) $deliveryId, 'cancelled_by' => $cancelledBy]
        );
    }

    // ── Core single-token send — returns FCM status string ────────────────────

    /**
     * @return string  'SUCCESS' | FCM error code | 'HTTP_ERROR_{status}' | 'EXCEPTION'
     */
    private function sendOne(
        string  $fcmToken,
        string  $title,
        string  $body,
        array   $data = [],
        string  $platform = 'android'
    ): string {
        $projectId = $this->projectId();
        $token     = $this->token();

        if (! $projectId || ! $token) return 'NO_CREDENTIALS';

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $type = $data['type'] ?? '';
        [$soundName, $channelId] = self::TYPE_SOUNDS[$type]
            ?? match (true) {
                str_starts_with($type, 'shop_')  => [null, 'shop_updates'],
                str_starts_with($type, 'admin_') => [null, 'admin_alerts'],
                default                          => [null, 'general'],
            };
        $iosSound = $soundName ? "{$soundName}.wav" : 'default';

        $stringData = array_map('strval', $data + ['sound' => $soundName ?? 'default']);

        $payload = [
            'message' => [
                'token'        => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data'    => $stringData,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound'               => 'default',
                        'channel_id'          => $channelId,
                        'click_action'        => 'FLUTTER_NOTIFICATION_CLICK',
                        'notification_count'  => 1,
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => $iosSound,
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                return 'SUCCESS';
            }

            // Parse FCM v1 error
            $error = $response->json('error.details.0.errorCode')
                ?? $response->json('error.status')
                ?? ('HTTP_ERROR_' . $response->status());

            Log::warning('[FCM] Send failed', [
                'token'  => substr($fcmToken, 0, 20) . '...',
                'status' => $response->status(),
                'error'  => $error,
            ]);

            return (string) $error;

        } catch (Throwable $e) {
            Log::error('[FCM] Exception', ['error' => $e->getMessage()]);
            return 'EXCEPTION';
        }
    }

    // ── Notification log ──────────────────────────────────────────────────────

    private function logNotification(
        User          $user,
        LocalizedText $title,
        LocalizedText $body,
        array         $data,
        string        $fcmToken,
        string        $fcmStatus
    ): void {
        try {
            PushNotification::create([
                'user_id'    => $user->id,
                'title'      => $title->for($user),
                'title_en'   => $title->in('en'),
                'title_kh'   => $title->in('kh'),
                'title_zh'   => $title->in('zh'),
                'body'       => $body->for($user),
                'body_en'    => $body->in('en'),
                'body_kh'    => $body->in('kh'),
                'body_zh'    => $body->in('zh'),
                'type'       => $data['type'] ?? null,
                'payload'    => $data,
                'fcm_token'  => $fcmToken,
                'fcm_status' => $fcmStatus,
                'status'     => $fcmStatus === 'SUCCESS' ? 'sent' : 'failed',
            ]);
        } catch (Throwable $e) {
            Log::error('[FCM] Log failed', ['error' => $e->getMessage()]);
        }
    }

    private function isDeadToken(string $fcmStatus): bool
    {
        foreach (self::DEAD_TOKEN_ERRORS as $dead) {
            if (str_contains($fcmStatus, $dead)) return true;
        }
        return false;
    }

    // ── Auth token ────────────────────────────────────────────────────────────

    private function token(): ?string
    {
        if ($this->accessToken && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }

        try {
            $path = $this->credentialsPath();
            if (! $path) return null;

            $keyFile     = json_decode(file_get_contents($path), true);
            $credentials = new ServiceAccountCredentials(self::SCOPES, $keyFile);
            $result      = $credentials->fetchAuthToken();

            $this->accessToken = $result['access_token'] ?? null;
            $this->tokenExpiry = time() + ($result['expires_in'] ?? 3600);
        } catch (Throwable $e) {
            report($e);
            return null;
        }

        return $this->accessToken;
    }

    private function projectId(): ?string
    {
        if ($this->projectId) return $this->projectId;

        $path = $this->credentialsPath();
        if (! $path) return null;

        $json = json_decode(file_get_contents($path), true);
        return $this->projectId = $json['project_id'] ?? null;
    }

    private function credentialsPath(): ?string
    {
        $path = config('firebase.projects.app.credentials');
        if ($path && file_exists($path)) return $path;

        $storage = storage_path('app/auto-ride-supperapp-firebase.json');
        if (file_exists($storage)) return $storage;

        return null;
    }
}
