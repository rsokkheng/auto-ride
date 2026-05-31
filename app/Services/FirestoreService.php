<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Delivery;
use App\Models\Ride;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Firestore integration via REST API (no gRPC / ext-grpc required).
 *
 * Collections:
 *   bookings/      rides + deliveries  (type: 'ride' | 'delivery')
 *   chats/         conversation metadata + last_message preview
 *   drivers/       driver profile + availability
 *   drivers_live/  real-time GPS — updated on every location tick
 *   messages/      flat message collection  (filter by conversation_id)
 *   users/         user profile snapshot
 */
class FirestoreService
{
    private const SCOPES = ['https://www.googleapis.com/auth/datastore'];

    private ?string $projectId   = null;
    private ?string $accessToken = null;
    private int     $tokenExpiry = 0;

    // ── Collections ───────────────────────────────────────────────────────────

    private const C_BOOKINGS      = 'bookings';
    private const C_CHATS         = 'chats';
    private const C_DRIVERS       = 'drivers';
    private const C_DRIVERS_LIVE  = 'drivers_live';
    private const C_MESSAGES      = 'messages';
    private const C_USERS         = 'users';

    // ── Users ─────────────────────────────────────────────────────────────────

    /**
     * users/{userId}
     * Sync on register, login, and profile update.
     */
    public function syncUser(User $user): void
    {
        $this->set(self::C_USERS, (string) $user->id, [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'avatar_url' => $user->avatar_url,
            'available'  => (bool) $user->available,
            'rating'     => (float) ($user->rating ?? 0),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    // ── Drivers ───────────────────────────────────────────────────────────────

    /**
     * drivers/{driverId}
     * Profile + availability — updated when driver goes online/offline.
     */
    public function syncDriver(User $driver, ?float $lat = null, ?float $lng = null): void
    {
        $this->set(self::C_DRIVERS, (string) $driver->id, [
            'id'        => $driver->id,
            'name'      => $driver->name,
            'phone'     => $driver->phone,
            'avatar_url'=> $driver->avatar_url,
            'available' => (bool) $driver->available,
            'rating'    => (float) ($driver->rating ?? 0),
            'lat'       => $lat ?? ($driver->current_latitude  ? (float) $driver->current_latitude  : null),
            'lng'       => $lng ?? ($driver->current_longitude ? (float) $driver->current_longitude : null),
            'updated_at'=> now()->toIso8601String(),
        ]);
    }

    /**
     * drivers_live/{driverId}
     * Raw GPS tick — Flutter uses this for smooth map marker animation.
     * Only write fields that change on every tick.
     */
    public function syncDriverLive(User $driver, float $lat, float $lng, ?float $speed = null, ?float $heading = null): void
    {
        $this->set(self::C_DRIVERS_LIVE, (string) $driver->id, [
            'driver_id'  => $driver->id,
            'name'       => $driver->name,
            'available'  => (bool) $driver->available,
            'lat'        => $lat,
            'lng'        => $lng,
            'speed'      => $speed,
            'heading'    => $heading,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    // ── Bookings (Rides + Deliveries) ─────────────────────────────────────────

    /**
     * bookings/{rideId}
     * type = 'ride'
     */
    public function syncRide(Ride $ride): void
    {
        $driver = $ride->driver;

        $this->set(self::C_BOOKINGS, 'ride_' . $ride->id, [
            'type'            => 'ride',
            'id'              => $ride->id,
            'status'          => $ride->status,
            'passenger_id'    => $ride->passenger_id,
            'driver_id'       => $ride->driver_id,
            'pickup_address'  => $ride->pickup_address,
            'dropoff_address' => $ride->dropoff_address,
            'fare'            => $ride->fare,
            'surge_multiplier'=> (float) ($ride->surge_multiplier ?? 1.0),
            'driver'          => $driver ? [
                'id'      => $driver->id,
                'name'    => $driver->name,
                'phone'   => $driver->phone,
                'lat'     => $driver->current_latitude  ? (float) $driver->current_latitude  : null,
                'lng'     => $driver->current_longitude ? (float) $driver->current_longitude : null,
            ] : null,
            'updated_at'      => now()->toIso8601String(),
        ]);
    }

    /**
     * Patch only the driver's embedded location inside a ride booking.
     * Called on every GPS tick — avoids rewriting the whole document.
     */
    public function updateRideDriverLocation(int $rideId, float $lat, float $lng, ?float $heading = null): void
    {
        $this->patch(self::C_BOOKINGS, 'ride_' . $rideId, [
            'driver.lat'     => $lat,
            'driver.lng'     => $lng,
            'driver.heading' => $heading,
            'updated_at'     => now()->toIso8601String(),
        ]);
    }

    /**
     * bookings/{deliveryId}
     * type = 'delivery'
     */
    public function syncDelivery(Delivery $delivery): void
    {
        $driver = $delivery->driver;

        $this->set(self::C_BOOKINGS, 'delivery_' . $delivery->id, [
            'type'            => 'delivery',
            'id'              => $delivery->id,
            'status'          => $delivery->status,
            'sender_id'       => $delivery->sender_id,
            'driver_id'       => $delivery->driver_id,
            'recipient_name'  => $delivery->recipient_name,
            'recipient_phone' => $delivery->recipient_phone,
            'pickup_address'  => $delivery->pickup_address,
            'dropoff_address' => $delivery->dropoff_address,
            'fee'             => $delivery->fee,
            'surge_multiplier'=> (float) ($delivery->surge_multiplier ?? 1.0),
            'assigned_at'     => $delivery->assigned_at?->toIso8601String(),
            'driver'          => $driver ? [
                'id'      => $driver->id,
                'name'    => $driver->name,
                'phone'   => $driver->phone,
                'lat'     => $driver->current_latitude  ? (float) $driver->current_latitude  : null,
                'lng'     => $driver->current_longitude ? (float) $driver->current_longitude : null,
            ] : null,
            'updated_at'      => now()->toIso8601String(),
        ]);
    }

    /**
     * Patch only the driver's embedded location inside a delivery booking.
     */
    public function updateDeliveryDriverLocation(int $deliveryId, float $lat, float $lng, ?float $heading = null): void
    {
        $this->patch(self::C_BOOKINGS, 'delivery_' . $deliveryId, [
            'driver.lat'     => $lat,
            'driver.lng'     => $lng,
            'driver.heading' => $heading,
            'updated_at'     => now()->toIso8601String(),
        ]);
    }

    // ── Chats ─────────────────────────────────────────────────────────────────

    /**
     * chats/{conversationId}
     * Conversation metadata + last_message preview for chat list.
     */
    public function syncConversation(ChatConversation $conversation, ?ChatMessage $lastMessage = null): void
    {
        $passenger = $conversation->passenger;
        $driver    = $conversation->driver;

        $this->set(self::C_CHATS, (string) $conversation->id, [
            'id'           => $conversation->id,
            'passenger_id' => $conversation->passenger_id,
            'driver_id'    => $conversation->driver_id,
            'topic'        => $conversation->topic,
            'status'       => $conversation->status,
            'passenger'    => $passenger ? ['id' => $passenger->id, 'name' => $passenger->name, 'avatar_url' => $passenger->avatar_url] : null,
            'driver'       => $driver    ? ['id' => $driver->id,    'name' => $driver->name,    'avatar_url' => $driver->avatar_url]    : null,
            'last_message' => $lastMessage ? [
                'text'      => $lastMessage->message,
                'sender_id' => $lastMessage->sender_id,
                'time'      => $lastMessage->created_at->toIso8601String(),
            ] : null,
            'updated_at'   => now()->toIso8601String(),
        ]);
    }

    /**
     * messages/{messageId}
     * Flat collection — Flutter queries by conversation_id + orderBy created_at.
     *
     * Flutter:
     *   FirebaseFirestore.instance
     *     .collection('messages')
     *     .where('conversation_id', isEqualTo: convId)
     *     .orderBy('created_at')
     *     .snapshots();
     */
    public function syncMessage(ChatMessage $message): void
    {
        $sender = $message->sender;

        $this->set(self::C_MESSAGES, (string) $message->id, [
            'id'              => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id'       => $message->sender_id,
            'sender_name'     => $sender?->name,
            'sender_avatar'   => $sender?->avatar_url,
            'message'         => $message->message,
            'read_at'         => $message->read_at?->toIso8601String(),
            'created_at'      => $message->created_at->toIso8601String(),
        ]);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    private function credentialsPath(): ?string
    {
        $path = env('FIREBASE_CREDENTIALS');
        return ($path && file_exists($path)) ? $path : null;
    }

    private function projectId(): ?string
    {
        if ($this->projectId) return $this->projectId;
        $path = $this->credentialsPath();
        if (! $path) return null;
        $json = json_decode(file_get_contents($path), true);
        return $this->projectId = $json['project_id'] ?? null;
    }

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

    // ── REST helpers ──────────────────────────────────────────────────────────

    private function set(string $collection, string $docId, array $data): void
    {
        $projectId = $this->projectId();
        $token     = $this->token();
        if (! $projectId || ! $token) return;

        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$docId}";

        try {
            Http::withToken($token)
                ->patch($url, ['fields' => $this->toFirestoreFields($data)]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function patch(string $collection, string $docId, array $data): void
    {
        $projectId = $this->projectId();
        $token     = $this->token();
        if (! $projectId || ! $token) return;

        $fieldPaths = implode(',', array_map(fn($k) => urlencode($k), array_keys($data)));
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents"
             . "/{$collection}/{$docId}?updateMask.fieldPaths={$fieldPaths}";

        try {
            Http::withToken($token)
                ->patch($url, ['fields' => $this->toFirestoreFields($data)]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function toFirestoreFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->toFirestoreValue($value);
        }
        return $fields;
    }

    private function toFirestoreValue(mixed $value): array
    {
        return match (true) {
            is_null($value)   => ['nullValue'    => null],
            is_bool($value)   => ['booleanValue' => $value],
            is_int($value)    => ['integerValue'  => (string) $value],
            is_float($value)  => ['doubleValue'   => $value],
            is_string($value) => ['stringValue'   => $value],
            is_array($value)  => ['mapValue'      => ['fields' => $this->toFirestoreFields($value)]],
            default           => ['stringValue'   => (string) $value],
        };
    }
}
