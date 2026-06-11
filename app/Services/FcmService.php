<?php

namespace App\Services;

use App\Models\PushNotification;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
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

    private ?string $accessToken = null;
    private int     $tokenExpiry = 0;
    private ?string $projectId   = null;

    // ── Public send helpers ───────────────────────────────────────────────────

    /**
     * Send to a single user by their User model.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $user->fcm_token) return;
        $this->send($user->fcm_token, $title, $body, $data);
        $this->storeNotification($user->id, $title, $body, $data);
    }

    /**
     * Send to multiple users at once.
     */
    public function sendToUsers(array $users, string $title, string $body, array $data = []): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $data);
        }
    }

    // ── Ride notification helpers ─────────────────────────────────────────────

    public function rideRequested(User $driver, int $rideId, string $pickup, string $dropoff): void
    {
        $this->sendToUser($driver,
            '🚗 New Ride Request',
            "{$pickup} → {$dropoff}",
            ['type' => 'ride_requested', 'ride_id' => (string) $rideId]
        );
    }

    public function rideAccepted(User $passenger, int $rideId, string $driverName): void
    {
        $this->sendToUser($passenger,
            '✅ Driver Found',
            "{$driverName} is on the way to pick you up.",
            ['type' => 'ride_accepted', 'ride_id' => (string) $rideId]
        );
    }

    public function driverArrived(User $passenger, int $rideId, string $driverName): void
    {
        $this->sendToUser($passenger,
            '📍 Driver Arrived',
            "{$driverName} has arrived at your pickup location.",
            ['type' => 'driver_arrived', 'ride_id' => (string) $rideId]
        );
    }

    public function rideStarted(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            '🚀 Trip Started',
            'Your trip is now in progress. Have a safe journey!',
            ['type' => 'ride_started', 'ride_id' => (string) $rideId]
        );
    }

    public function rideCompleted(User $passenger, int $rideId, int $fare): void
    {
        $this->sendToUser($passenger,
            '🏁 Trip Completed',
            "You have arrived. Fare: {$fare} KHR.",
            ['type' => 'ride_completed', 'ride_id' => (string) $rideId, 'fare' => (string) $fare]
        );
    }

    public function rideCancelledByDriver(User $passenger, int $rideId): void
    {
        $this->sendToUser($passenger,
            '❌ Ride Cancelled',
            'Your driver has cancelled the ride. Please book again.',
            ['type' => 'ride_cancelled', 'ride_id' => (string) $rideId, 'cancelled_by' => 'driver']
        );
    }

    public function rideCancelledByPassenger(User $driver, int $rideId): void
    {
        $this->sendToUser($driver,
            '❌ Ride Cancelled',
            'The passenger has cancelled the ride.',
            ['type' => 'ride_cancelled', 'ride_id' => (string) $rideId, 'cancelled_by' => 'passenger']
        );
    }

    // ── Delivery notification helpers ─────────────────────────────────────────

    public function deliveryRequested(User $driver, int $deliveryId, string $pickup, string $dropoff): void
    {
        $this->sendToUser($driver,
            '📦 New Delivery Request',
            "{$pickup} → {$dropoff}",
            ['type' => 'delivery_requested', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryAccepted(User $sender, int $deliveryId, string $driverName): void
    {
        $this->sendToUser($sender,
            '✅ Driver Assigned',
            "{$driverName} has accepted your delivery.",
            ['type' => 'delivery_accepted', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryPickedUp(User $sender, int $deliveryId, string $driverName): void
    {
        $this->sendToUser($sender,
            '🚚 Package Picked Up',
            "{$driverName} has picked up your package and is on the way.",
            ['type' => 'delivery_picked_up', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryCompleted(User $sender, int $deliveryId): void
    {
        $this->sendToUser($sender,
            '✅ Delivery Completed',
            'Your package has been delivered successfully.',
            ['type' => 'delivery_completed', 'delivery_id' => (string) $deliveryId]
        );
    }

    public function deliveryCancelled(User $user, int $deliveryId, string $cancelledBy): void
    {
        $this->sendToUser($user,
            '❌ Delivery Cancelled',
            $cancelledBy === 'driver'
                ? 'Your driver cancelled the delivery. Please book again.'
                : 'The delivery has been cancelled.',
            ['type' => 'delivery_cancelled', 'delivery_id' => (string) $deliveryId, 'cancelled_by' => $cancelledBy]
        );
    }

    // ── Core send ─────────────────────────────────────────────────────────────

    private function send(string $fcmToken, string $title, string $body, array $data = []): void
    {
        $projectId = $this->projectId();
        $token     = $this->token();

        if (! $projectId || ! $token) return;

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // FCM data values must all be strings
        $stringData = array_map('strval', $data);

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
                        'sound'        => 'default',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            Http::withToken($token)
                ->post($url, $payload);
        } catch (Throwable $e) {
            report($e);
        }
    }

    // ── Store in DB for in-app notification inbox ─────────────────────────────

    private function storeNotification(int $userId, string $title, string $body, array $data): void
    {
        try {
            PushNotification::create([
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'type'    => $data['type'] ?? null,
                'payload' => $data,
                'status'  => 'sent',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
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
