<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Short-lived proof that a guest shopper verified their phone by SMS OTP.
 *
 * Issued by POST /shop/guest-phone/verify (no account is created) and
 * required by resolveShopper() for guest orders/rentals. The token is an
 * encrypted {phone, expiry} pair, so it can't be forged or moved to another
 * number, and one verification covers several orders until it expires.
 */
final class GuestPhoneToken
{
    public const TTL_MINUTES = 30;

    /** '+855 12 345 678', '012345678' and '12345678' all map to '12345678'. */
    public static function canonical(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '855')) {
            $digits = substr($digits, 3);
        }

        return ltrim($digits, '0');
    }

    /** @return array{token: string, expires_at: string} */
    public static function issue(string $phone): array
    {
        $expires = now()->addMinutes(self::TTL_MINUTES);

        return [
            'token'      => Crypt::encryptString(json_encode([
                'p' => self::canonical($phone),
                'e' => $expires->timestamp,
            ])),
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    public static function matches(?string $token, string $phone): bool
    {
        if (! $token) {
            return false;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return false;
        }

        return is_array($data)
            && (int) ($data['e'] ?? 0) >= now()->timestamp
            && hash_equals((string) ($data['p'] ?? ''), self::canonical($phone));
    }
}
