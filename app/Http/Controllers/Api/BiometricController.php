<?php

namespace App\Http\Controllers\Api;

use App\Models\BiometricDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BiometricController extends ApiController
{
    /**
     * POST /v1/auth/biometric/register
     * Registers the device's public key for biometric auth.
     * Body: { device_id, device_name?, platform, public_key }
     */
    public function register(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'device_id'   => 'required|string|max:128',
            'device_name' => 'nullable|string|max:128',
            'platform'    => 'required|in:ios,android',
            'public_key'  => 'required|string|max:2048',
        ]);

        $device = BiometricDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $data['device_id']],
            [
                'device_name' => $data['device_name'] ?? null,
                'platform'    => $data['platform'],
                'public_key'  => $data['public_key'],
                'active'      => true,
            ]
        );

        return $this->success([
            'id'          => $device->id,
            'device_id'   => $device->device_id,
            'device_name' => $device->device_name,
            'platform'    => $device->platform,
        ], 'Biometric device registered.');
    }

    /**
     * POST /v1/auth/biometric/challenge
     * Returns a one-time challenge for the device to sign.
     * Body: { device_id }
     */
    public function challenge(Request $request)
    {
        $data = $request->validate(['device_id' => 'required|string|max:128']);

        $device = BiometricDevice::where('device_id', $data['device_id'])
            ->where('active', true)
            ->first();

        if (! $device) {
            return response()->json(['data' => null, 'message' => 'Device not registered.'], 404);
        }

        $challenge = Str::random(64);
        $device->update([
            'challenge'            => hash('sha256', $challenge),
            'challenge_expires_at' => now()->addMinutes(2),
        ]);

        return $this->success(['challenge' => $challenge, 'expires_in' => 120]);
    }

    /**
     * POST /v1/auth/biometric/verify
     * Verifies the signed challenge and returns an auth token.
     * Body: { device_id, signed_challenge }
     */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'device_id'        => 'required|string|max:128',
            'signed_challenge'  => 'required|string',
        ]);

        $device = BiometricDevice::with('user')
            ->where('device_id', $data['device_id'])
            ->where('active', true)
            ->first();

        if (! $device || ! $device->challenge_expires_at || now()->gt($device->challenge_expires_at)) {
            return response()->json(['data' => null, 'message' => 'Challenge expired or invalid device.'], 401);
        }

        // Verify: client hashes challenge with SHA-256 and sends it back
        $expected = $device->challenge;
        $received = hash('sha256', $data['signed_challenge']);

        if (! hash_equals($expected, $received)) {
            return response()->json(['data' => null, 'message' => 'Biometric verification failed.'], 401);
        }

        $user = $device->user;
        $token = bin2hex(random_bytes(40));
        $user->update([
            'api_token'                => $token,
            'refresh_token'            => bin2hex(random_bytes(40)),
            'token_expires_at'         => now()->addMinutes(60),
            'refresh_token_expires_at' => now()->addDays(30),
        ]);

        $device->update(['challenge' => null, 'challenge_expires_at' => null, 'last_used_at' => now()]);

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => 3600,
            'user'         => array_merge($user->fresh()->toArray(), ['avatar_url' => $user->avatar_url]),
        ]);
    }

    /** GET /v1/auth/biometric/devices — list user's registered devices */
    public function devices(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $devices = BiometricDevice::where('user_id', $user->id)
            ->select(['id', 'device_id', 'device_name', 'platform', 'active', 'last_used_at', 'created_at'])
            ->orderByDesc('id')
            ->get();

        return $this->success($devices);
    }

    /** DELETE /v1/auth/biometric/devices/{device} */
    public function revoke(Request $request, BiometricDevice $device)
    {
        $user = $this->authUser($request);
        if (! $user || $device->user_id !== $user->id) return $this->unauthorized();

        $device->update(['active' => false]);

        return $this->success(null, 'Device revoked.');
    }
}
