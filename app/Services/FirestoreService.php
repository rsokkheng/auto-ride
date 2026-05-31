<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Firestore integration via REST API (no gRPC required).
 *
 * Authenticates using a Google service account JSON file, caches the
 * OAuth access token, and writes documents to Firestore using the
 * projects.databases.documents REST endpoint.
 *
 * All write methods are fire-and-forget: errors are reported but never
 * propagate to the caller so the API response is never broken.
 */
class FirestoreService
{
    private const SCOPES = ['https://www.googleapis.com/auth/datastore'];

    private ?string $projectId   = null;
    private ?string $accessToken = null;
    private int     $tokenExpiry = 0;

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
        // Return cached token if still valid (with 60-second buffer).
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

    // ── Public sync methods ───────────────────────────────────────────────────

    public function syncDriver(User $driver, ?float $lat = null, ?float $lng = null, ?float $speed = null, ?float $heading = null): void
    {
        $this->set('drivers', (string) $driver->id, [
            'id'        => $driver->id,
            'name'      => $driver->name,
            'phone'     => $driver->phone,
            'available' => (bool) $driver->available,
            'lat'       => $lat ?? ($driver->current_latitude  ? (float) $driver->current_latitude  : null),
            'lng'       => $lng ?? ($driver->current_longitude ? (float) $driver->current_longitude : null),
            'speed'     => $speed,
            'heading'   => $heading,
            'updated_at'=> now()->toIso8601String(),
        ]);
    }

    public function syncRide(Ride $ride): void
    {
        $driver = $ride->driver;

        $this->set('rides', (string) $ride->id, [
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

    public function updateRideDriverLocation(int $rideId, float $lat, float $lng, ?float $heading = null): void
    {
        $this->patch('rides', (string) $rideId, [
            'driver.lat'     => $lat,
            'driver.lng'     => $lng,
            'driver.heading' => $heading,
            'updated_at'     => now()->toIso8601String(),
        ]);
    }

    public function syncDelivery(Delivery $delivery): void
    {
        $driver = $delivery->driver;

        $this->set('deliveries', (string) $delivery->id, [
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

    public function updateDeliveryDriverLocation(int $deliveryId, float $lat, float $lng, ?float $heading = null): void
    {
        $this->patch('deliveries', (string) $deliveryId, [
            'driver.lat'     => $lat,
            'driver.lng'     => $lng,
            'driver.heading' => $heading,
            'updated_at'     => now()->toIso8601String(),
        ]);
    }

    // ── REST helpers ──────────────────────────────────────────────────────────

    /** Full document overwrite (creates if not exists). */
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

    /** Partial field update using Firestore fieldMask. */
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

    /**
     * Convert a plain PHP array into Firestore REST API field format.
     *
     * @see https://firebase.google.com/docs/firestore/reference/rest/v1/Value
     */
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
