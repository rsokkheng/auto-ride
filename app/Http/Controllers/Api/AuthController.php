<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone'    => 'nullable|string|max:24',
            'role'     => 'nullable|in:passenger,driver',
        ]);

        $data['role']           = $data['role'] ?? 'passenger';
        $data['wallet_balance'] = 0;

        $user = User::create($data);

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

    public function getProfile(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        return $this->success(['user' => $user]);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:24',
            'status_note' => 'sometimes|nullable|string|max:255',
        ]);

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

    protected function tokenResponse(User $user): array
    {
        return [
            'user'                     => $user->fresh(),
            'access_token'             => $user->api_token,
            'refresh_token'            => $user->refresh_token,
            'token_type'               => 'Bearer',
            'expires_in'               => self::ACCESS_TTL * 60,
            'refresh_expires_in'       => self::REFRESH_TTL * 60,
        ];
    }
}
