<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\DriverDevice;
use App\Models\PhoneOtp;
use App\Models\Ride;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;

class AuthController extends ApiController
{
    // Access token TTL: 24 hours
    protected const ACCESS_TTL  = 1440;
    // Refresh token TTL: 30 days
    protected const REFRESH_TTL = 43200;

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'username'     => 'nullable|string|max:64|unique:users,username|alpha_dash',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            'phone'        => 'nullable|string|max:24',
            'role'         => 'nullable|in:passenger,driver,partner',
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
        $data = $request->validate([
            'login'    => 'required_without:email|nullable|string',
            'email'    => 'required_without:login|nullable|string',
            'password' => 'required|string',
        ]);

        $login = $data['login'] ?? $data['email'];

        // Resolve user by email, username, or normalized phone
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $login)->first();
        } else {
            $phone = $this->normalizePhone($login);
            $user  = User::where(function ($q) use ($login, $phone) {
                $q->where('username', $login)->orWhere('phone', $phone);
            })->latest()->first();
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
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

        $totalTrips = $user->role === 'driver'
            ? Ride::where('driver_id', $user->id)->where('status', 'completed')->count()
                + Delivery::where('driver_id', $user->id)->where('status', 'completed')->count()
            : Ride::where('passenger_id', $user->id)->where('status', 'completed')->count();

        return $this->success([
            'user' => array_merge($user->toArray(), [
                'avatar_url'  => $user->avatar_url,
                'total_trips' => $totalTrips,
            ]),
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
            'username'     => 'sometimes|nullable|string|max:64|unique:users,username,' . $user->id . '|alpha_dash',
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

    /**
     * POST /v1/auth/phone/verify
     *
     * Flutter gets a Firebase ID token after the user passes Firebase Phone Auth
     * (real SMS OTP handled entirely by Firebase SDK on device).
     * This endpoint verifies that token server-side, marks the phone verified,
     * and returns a full API session.
     *
     * Body: { "firebase_id_token": "eyJhbGci..." }
     */
    public function verifyPhone(Request $request)
    {
        $data = $request->validate([
            'firebase_id_token' => 'required|string',
        ]);

        try {
            $auth      = app(FirebaseAuth::class);
            $verified  = $auth->verifyIdToken($data['firebase_id_token']);
            $claims    = $verified->claims();
            $phone     = $claims->get('phone_number');
        } catch (RevokedIdToken $e) {
            return response()->json(['data' => null, 'message' => 'Firebase token has been revoked. Please re-authenticate.'], 401);
        } catch (FailedToVerifyToken $e) {
            return response()->json(['data' => null, 'message' => 'Invalid Firebase token: ' . $e->getMessage()], 401);
        } catch (\Throwable $e) {
            return response()->json(['data' => null, 'message' => 'Token verification failed.'], 401);
        }

        if (empty($phone)) {
            return response()->json(['data' => null, 'message' => 'Firebase token does not contain a phone number.'], 422);
        }

        // Find existing user by phone or create a new passenger account
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            $user = User::create([
                'name'              => 'User ' . substr($phone, -4),
                'email'             =>  ltrim($phone, '+') . '@gmail.com',
                'phone'             => $phone,
                'password'          => Str::random(40),
                'role'              => 'passenger',
                'wallet_balance'    => 0,
                'phone_verified_at' => now(),
            ]);
        } else {
            $user->update(['phone_verified_at' => now()]);
        }

        $this->issueTokens($user);

        return $this->success(array_merge($this->tokenResponse($user), [
            'phone_verified' => true,
            'phone'          => $phone,
        ]));
    }

    public function sendOTP(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:24',
        ]);

        try {
            $phone = $this->normalizePhone($data['phone']);
            $code  = rand(100000, 999999);

            PhoneOtp::where('phone', $phone)->delete();

            PhoneOtp::create([
                'phone'        => $phone,
                'otp_hash'     => Hash::make($code),
                'expires_at'   => now()->addMinutes(3),
                'last_sent_at' => now(),
                'verified_at'  => null,
            ]);

            $sent = app(SmsService::class)->send(
                $phone,
                "Your ROTEH OTP is: {$code}. Valid for 3 minutes."
            );

            $response = [
                'message'  => $sent ? 'OTP sent successfully' : 'OTP created. SMS delivery failed, use code below.',
                'phone'    => $phone,
                'sms_sent' => $sent,
            ];

            // Always return code if SMS failed, or in debug mode
            if (! $sent || config('app.debug')) {
                $response['code'] = $code;
            }

            return $this->success($response);

        } catch (\Throwable $e) {
            Log::error('[sendOTP] ' . $e->getMessage(), [
                'phone' => $data['phone'],
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);

            $message = config('app.debug') ? $e->getMessage() : 'Server error. Please try again.';
            return response()->json(['message' => $message], 500);
        }
    }

    public function verifyOTP(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:24',
            'code'  => 'required|string|max:8',
        ]);

        $phone = $this->normalizePhone($data['phone']);

        $record = PhoneOtp::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $record || ! Hash::check($data['code'], $record->otp_hash)) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        $record->update(['verified_at' => now()]);

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            $user = User::create([
                'name'              => 'User ' . substr($phone, -4),
                'email'             =>  $phone . '@gmail.com',
                'phone'             => $phone,
                'password'          => Str::random(40),
                'role'              => 'passenger',
                'wallet_balance'    => 0,
                'phone_verified_at' => now(),
            ]);
        } else {
            $user->update(['phone_verified_at' => now()]);
        }

        $this->issueTokens($user);

        return $this->success(array_merge($this->tokenResponse($user), [
            'phone_verified' => true,
            'phone'          => $phone,
        ]));
    }

    private function normalizePhone(string $phone): string
    {
        // Strip leading + or spaces
        $phone = ltrim(trim($phone), '+');

        // Strip Cambodia country code 855
        if (str_starts_with($phone, '855')) {
            $phone = substr($phone, 3);
        }

        // Ensure local format starts with 0
        if (! str_starts_with($phone, '0')) {
            $phone = '0' . $phone;
        }

        return $phone;
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

    /** GET /v1/auth/fcm-token — return current FCM token for the authenticated user. */
    public function getFcmToken(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        return $this->success([
            'fcm_token' => $user->fcm_token,
            'has_token' => ! is_null($user->fcm_token),
        ]);
    }

    /** GET /v1/driver/device-token — return all registered devices for the driver. */
    public function getDeviceTokens(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $devices = DriverDevice::where('user_id', $user->id)
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($d) => [
                'id'           => $d->id,
                'token'        => $d->token,
                'platform'     => $d->platform,
                'device_id'    => $d->device_id,
                'app_version'  => $d->app_version,
                'is_active'    => $d->is_active,
                'last_used_at' => $d->last_used_at,
                'created_at'   => $d->created_at,
            ]);

        return $this->success([
            'fcm_token' => $user->fcm_token,
            'devices'   => $devices,
        ]);
    }

    public function saveFcmToken(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'fcm_token'   => 'required|string|max:512',
            'platform'    => 'nullable|in:android,ios',
            'device_id'   => 'nullable|string|max:255',
            'app_version' => 'nullable|string|max:32',
        ]);

        $token     = $data['fcm_token'];
        $platform  = $data['platform'] ?? 'android';
        // Use provided device_id or derive a stable one from the token
        $deviceId  = $data['device_id'] ?? substr(md5($token), 0, 16);

        $user->update(['fcm_token' => $token]);

        // Auto-register into driver_devices so sendToDriver() works without
        // requiring the app to call the new /driver/device-token endpoint.
        if ($user->role === 'driver') {
            DriverDevice::updateOrCreate(
                ['token' => $token],
                [
                    'user_id'      => $user->id,
                    'platform'     => $platform,
                    'device_id'    => $deviceId,
                    'app_version'  => $data['app_version'] ?? null,
                    'is_active'    => true,
                    'last_used_at' => now(),
                ]
            );
        }

        return $this->success(['message' => 'FCM token saved.']);
    }

    /**
     * POST /v1/driver/device-token
     * Register or refresh a device FCM token for multi-device driver notifications.
     * Body: { token, platform: android|ios, device_id?, app_version? }
     */
    public function saveDeviceToken(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'token'       => 'required|string|max:512',
            'platform'    => 'required|in:android,ios',
            'device_id'   => 'nullable|string|max:255',
            'app_version' => 'nullable|string|max:32',
        ]);

        $deviceId = $data['device_id'] ?? substr(md5($data['token']), 0, 16);

        // Upsert by token — reactivate if previously deactivated
        DriverDevice::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'      => $user->id,
                'platform'     => $data['platform'],
                'device_id'    => $deviceId,
                'app_version'  => $data['app_version'] ?? null,
                'is_active'    => true,
                'last_used_at' => now(),
            ]
        );

        // Keep users.fcm_token in sync for backward compatibility
        $user->update(['fcm_token' => $data['token']]);

        return $this->success(['message' => 'Device token registered.']);
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
                'email'           => $email ?? $data['provider'] . '_' . $socialId . '@gmail.com',
                'password'        => Str::random(40),
                'role'            => 'passenger',
                'social_provider' => $data['provider'],
                'social_id'       => $socialId,
                'avatar'          => $avatar,
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
