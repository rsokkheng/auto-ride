<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    // Access token TTL: 30 minutes
    protected const ACCESS_TTL  = 30;
    // Refresh token TTL: 1 hour
    protected const REFRESH_TTL = 60;

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            'phone'        => 'nullable|string|max:24',
            'role'         => 'nullable|in:passenger,driver',
            'driver_type'  => 'nullable|in:owner,employee,rental',
            'company_name' => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:100',
            'referred_by_code' => 'nullable|string|max:12',
        ]);

        $data['role']           = $data['role'] ?? 'passenger';
        $data['wallet_balance'] = 0;

        // driver_type only applies to drivers; default to owner.
        if ($data['role'] === 'driver') {
            $data['driver_type']     = $data['driver_type'] ?? 'owner';
            $data['approval_status'] = 'pending';
            if (! in_array($data['driver_type'], ['employee', 'rental'])) {
                $data['company_name'] = null;
            }
        } else {
            unset($data['driver_type'], $data['company_name']);
        }

        // Handle referral code.
        $referralCode = $data['referred_by_code'] ?? null;
        unset($data['referred_by_code']);

        $user = User::create($data);

        // Link referral if a valid code was provided.
        if ($referralCode) {
            $referrer = \App\Models\User::where('referral_code', strtoupper($referralCode))->first();
            if ($referrer && $referrer->id !== $user->id) {
                \App\Models\Referral::create([
                    'referrer_id' => $referrer->id,
                    'referee_id'  => $user->id,
                    'status'      => 'pending',
                    'bonus_khr'   => \App\Http\Controllers\Api\ReferralController::REFERRAL_BONUS_KHR,
                ]);
                $user->update(['referred_by' => $referrer->id]);
            }
        }

        $this->issueTokens($user);

        return $this->success($this->tokenResponse($user), 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $this->issueTokens($user);

        return $this->success($this->tokenResponse($user));
    }

    public function refreshToken(Request $request)
    {
        $token = $request->input('refresh_token') ?? $request->bearerToken();

        $user = User::where('refresh_token', $token)->first();

        if (! $user) {
            return $this->unauthorized('Invalid refresh token.');
        }

        if ($user->refresh_token_expires_at && now()->isAfter($user->refresh_token_expires_at)) {
            return $this->unauthorized('Refresh token expired. Please log in again.');
        }

        $this->issueTokens($user);

        return $this->success($this->tokenResponse($user));
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken() ?? $request->input('api_token');
        $user  = User::where('api_token', $token)->first();

        if (! $user) {
            return $this->unauthorized();
        }

        $user->update([
            'api_token'               => null,
            'refresh_token'           => null,
            'token_expires_at'        => null,
            'refresh_token_expires_at'=> null,
        ]);

        return $this->success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return $this->getProfile($request);
    }

    /**
     * GET /v1/auth/avatar
     *
     * Returns the authenticated user's profile photo URL.
     * avatar_url is null when no photo has been uploaded.
     */
    public function getAvatar(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        return $this->success([
            'avatar_url' => $user->avatar_url,
            'has_avatar' => ! is_null($user->avatar_url),
        ]);
    }

    public function getProfile(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        return $this->success([
            'user' => array_merge($user->toArray(), ['avatar_url' => $user->avatar_url]),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'email'        => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'        => 'sometimes|string|max:24',
            'status_note'  => 'sometimes|nullable|string|max:255',
            'driver_type'  => 'sometimes|nullable|in:owner,company_staff,rental',
            'company_name' => 'sometimes|nullable|string|max:255',
        ]);

        // Enforce company_name only makes sense for company_staff / rental.
        if (isset($data['driver_type']) && ! in_array($data['driver_type'], ['company_staff', 'rental'])) {
            $data['company_name'] = null;
        }

        $user->update($data);

        return $this->success(['user' => $user]);
    }

    public function sendOTP(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:24',
        ]);

        $code = rand(100000, 999999);
        $cacheKey = 'otp:' . $data['phone'];
        Cache::put($cacheKey, $code, now()->addMinutes(10));

        return $this->success([
            'message' => 'OTP sent',
            'phone' => $data['phone'],
            'code' => $code,
        ]);
    }

    public function verifyOTP(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:24',
            'code' => 'required|string|max:8',
        ]);

        $cacheKey = 'otp:' . $data['phone'];
        $storedCode = Cache::get($cacheKey);

        if (! $storedCode || (string) $storedCode !== $data['code']) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        Cache::forget($cacheKey);

        return $this->success(['message' => 'OTP verified successfully']);
    }

    protected function issueTokens(User $user): void
    {
        $user->update([
            'api_token'                => Str::random(80),
            'refresh_token'            => Str::random(120),
            'token_expires_at'         => now()->addMinutes(self::ACCESS_TTL),
            'refresh_token_expires_at' => now()->addMinutes(self::REFRESH_TTL),
        ]);
    }

    public function saveFcmToken(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'fcm_token' => 'required|string|max:255',
        ]);

        $user->update(['fcm_token' => $data['fcm_token']]);

        return $this->success(['message' => 'FCM token saved.']);
    }

    // ── Social Login ─────────────────────────────────────────────────────────

    /** POST /v1/auth/social  Body: { provider: "google"|"facebook", token: "..." } */
    public function socialLogin(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|in:google,facebook',
            'token'    => 'required|string',
        ]);

        [$socialId, $name, $email, $avatar] = match ($data['provider']) {
            'google'   => $this->verifyGoogle($data['token']),
            'facebook' => $this->verifyFacebook($data['token']),
        };

        if (! $socialId) {
            return response()->json(['data' => null, 'message' => 'Invalid social token.'], 401);
        }

        $user = User::where('social_provider', $data['provider'])
                    ->where('social_id', $socialId)
                    ->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $user = User::create([
                'name'            => $name ?? 'User',
                'email'           => $email ?? $data['provider'] . '_' . $socialId . '@auto-ride.local',
                'password'        => Str::random(40),
                'role'            => 'passenger',
                'social_provider' => $data['provider'],
                'social_id'       => $socialId,
                'avatar'          => null,
                'api_token'       => bin2hex(random_bytes(40)),
                'refresh_token'   => bin2hex(random_bytes(40)),
                'token_expires_at'=> now()->addMinutes(self::ACCESS_TTL),
                'refresh_token_expires_at' => now()->addMinutes(self::REFRESH_TTL),
            ]);
        } else {
            $user->update([
                'social_provider'          => $data['provider'],
                'social_id'                => $socialId,
                'api_token'                => bin2hex(random_bytes(40)),
                'refresh_token'            => bin2hex(random_bytes(40)),
                'token_expires_at'         => now()->addMinutes(self::ACCESS_TTL),
                'refresh_token_expires_at' => now()->addMinutes(self::REFRESH_TTL),
            ]);
        }

        return $this->success($this->tokenResponse($user));
    }

    private function verifyGoogle(string $token): array
    {
        $res = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $token]);
        if (! $res->successful()) return [null, null, null, null];

        $payload  = $res->json();
        $audience = $payload['aud'] ?? '';
        if (! str_starts_with($audience, config('services.google.client_id', ''))) {
            return [null, null, null, null];
        }

        return [
            $payload['sub']     ?? null,
            $payload['name']    ?? null,
            $payload['email']   ?? null,
            $payload['picture'] ?? null,
        ];
    }

    private function verifyFacebook(string $token): array
    {
        $res = Http::get('https://graph.facebook.com/me', [
            'fields'       => 'id,name,email,picture',
            'access_token' => $token,
        ]);
        if (! $res->successful()) return [null, null, null, null];

        $data = $res->json();
        return [
            $data['id']              ?? null,
            $data['name']            ?? null,
            $data['email']           ?? null,
            $data['picture']['data']['url'] ?? null,
        ];
    }

    protected function tokenResponse(User $user): array
    {
        $fresh = $user->fresh();

        return [
            'user'                     => array_merge($fresh->toArray(), [
                'avatar_url' => $fresh->avatar_url,
            ]),
            'access_token'             => $user->api_token,
            'refresh_token'            => $user->refresh_token,
            'token_type'               => 'Bearer',
            'expires_in'               => self::ACCESS_TTL * 60,
            'refresh_expires_in'       => self::REFRESH_TTL * 60,
        ];
    }
}
