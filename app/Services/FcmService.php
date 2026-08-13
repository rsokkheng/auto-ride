<?php

namespace App\Services;

use App\Models\DriverDevice;
use App\Models\PushNotification;
use App\Models\User;
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

    private ?string $accessToken = null;
    private int     $tokenExpiry = 0;
    private ?string $projectId   = null;

    // ── Public send helpers ───────────────────────────────────────────────────

    /**
     * Send to a single user via their users.fcm_token (fallback / passenger use).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $user->fcm_token) return;
        $fcmStatus = $this->sendOne($user->fcm_token, $title, $body, $data);
        $this->logNotification($user->id, $title, $body, $data, $user->fcm_token, $fcmStatus);

        if ($this->isDeadToken($fcmStatus)) {
            $user->update(['fcm_token' => null]);
        }
    }

    /**
     * Send to a driver via all their registered devices in driver_devices.
     * Falls back to users.fcm_token if no devices are registered.
     */
    public function sendToDriver(User $driver, string $title, string $body, array $data = []): void
    {
        $devices = DriverDevice::where('user_id', $driver->id)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            // Fallback to legacy single token
            $this->sendToUser($driver, $title, $body, $data);
            return;
        }

        foreach ($devices as $device) {
            $fcmStatus = $this->sendOne($device->token, $title, $body, $data, $device->platform);
            $this->logNotification($driver->id, $title, $body, $data, $device->token, $fcmStatus);

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
     * Send to multiple users at once (passengers / broadcast).
     */
    public function sendToUsers(array $users, string $title, string $body, array $data = []): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $data);
        }
    }

    // ── Ride notification helpers ─────────────────────────────────────────────

    public function rideRequested(User $driver, int $rideId, string $pickup, string $dropoff, string $passengerName = '', int $fare = 0): void
    {
        $this->sendToDriver($driver,
            '🚕 មានការកក់ដំណើរថ្មី',
            'មានអ្នកដំណើរកំពុងស្នើសុំដំណើរ',
            [
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

    public function rideAccepted(User $passenger, int $rideId, string $driverName): void
    {
        $this->sendToUser($passenger,
            '🚕 អ្នកបើកបរបានទទួលដំណើរ',
            "{$driverName} កំពុងមករកអ្នក",
            ['type' => 'ride_accepted', 'booking_id' => (string) $rideId]
        );
    }

    public function rideRejected(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            'No Driver Available',
            'Your ride request could not be accepted. Please try again.',
            ['type' => 'ride_rejected', 'booking_id' => (string) $rideId]
        );
    }

    public function driverArrived(User $passenger, int $rideId, string $driverName): void
    {
        $this->sendToUser($passenger,
            '📍 អ្នកបើកបរបានដល់',
            "{$driverName} បានដល់កន្លែងទទួលអ្នក",
            ['type' => 'driver_arrived', 'booking_id' => (string) $rideId]
        );
    }

    public function rideStarted(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            '🚀 ដំណើរបានចាប់ផ្តើម',
            'ដំណើររបស់អ្នកកំពុងដំណើរការ។ សូមធ្វើដំណើរដោយសុវត្ថិភាព!',
            ['type' => 'ride_started', 'booking_id' => (string) $rideId]
        );
    }

    public function rideCompleted(User $passenger, int $rideId, int $fare): void
    {
        $this->sendToUser($passenger,
            '🏁 ដំណើរបានបញ្ចប់',
            "អ្នកបានដល់គោលដៅ។ ថ្លៃដំណើរ: {$fare} KHR.",
            ['type' => 'ride_completed', 'booking_id' => (string) $rideId, 'fare' => (string) $fare]
        );
    }

    public function rideCancelledByDriver(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            '❌ ដំណើរត្រូវបានលុបចោល',
            'អ្នកបើកបរបានលុបចោលដំណើរ។ សូមកក់ម្ដងទៀត។',
            ['type' => 'ride_cancelled', 'booking_id' => (string) $rideId, 'cancelled_by' => 'driver']
        );
    }

    public function rideCancelledByPassenger(User $driver, int $rideId): void
    {
        $this->sendToDriver($driver,
            '❌ ដំណើរត្រូវបានលុបចោល',
            'អ្នកដំណើរបានលុបចោលការកក់។',
            ['type' => 'ride_cancelled', 'booking_id' => (string) $rideId, 'cancelled_by' => 'passenger']
        );
    }

    // ── Delivery notification helpers ─────────────────────────────────────────

    public function deliveryRequested(User $driver, int $deliveryId, string $pickup, string $dropoff): void
    {
        $this->sendToDriver($driver,
            'New Delivery Request',
            "{$pickup} → {$dropoff}",
            ['type' => 'delivery_requested', 'delivery_id' => (string) $deliveryId, 'sound' => 'booking']
        );
    }

    public function deliveryAccepted(User $sender, int $deliveryId, string $driverName): void
    {
        $this->sendToUser($sender,
            'Driver Assigned',
            "{$driverName} has accepted your delivery.",
            ['type' => 'delivery_accepted', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryPickedUp(User $sender, int $deliveryId, string $driverName): void
    {
        $this->sendToUser($sender,
            'Package Picked Up',
            "{$driverName} has picked up your package and is on the way.",
            ['type' => 'delivery_picked_up', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryCompleted(User $sender, int $deliveryId): void
    {
        $this->sendToUser($sender,
            'Delivery Completed',
            'Your package has been delivered successfully.',
            ['type' => 'delivery_completed', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryCancelled(User $user, int $deliveryId, string $cancelledBy): void
    {
        $this->sendToUser($user,
            'Delivery Cancelled',
            $cancelledBy === 'driver'
                ? 'Your driver cancelled the delivery. Please book again.'
                : 'The delivery has been cancelled.',
            ['type' => 'delivery_cancelled', 'delivery_id' => (string) $deliveryId, 'cancelled_by' => $cancelledBy]
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

        $url        = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $stringData = array_map('strval', $data);
        $isBooking  = in_array($data['type'] ?? '', ['ride_requested', 'delivery_requested'], true);
        $iosSound   = $isBooking ? 'booking.wav' : 'default';

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
                        'channel_id'          => $isBooking ? 'booking_alerts' : 'general',
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
        int    $userId,
        string $title,
        string $body,
        array  $data,
        string $fcmToken,
        string $fcmStatus
    ): void {
        try {
            PushNotification::create([
                'user_id'    => $userId,
                'title'      => $title,
                'body'       => $body,
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
        $path = env('FIREBASE_CREDENTIALS');
        if ($path && file_exists($path)) return $path;

        $storage = storage_path('app/auto-ride-supperapp-firebase.json');
        if (file_exists($storage)) return $storage;

        return null;
    }
}
