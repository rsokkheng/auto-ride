<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A real Cambodian mobile number. Same prefix table as the app
 * (lib/utils/phone_utils.dart): 9-digit numbers for most operators, 10 digits
 * for the Metfone-style prefixes. Accepts +855 / 855 / 0 forms.
 *
 * Stops made-up numbers — above all on OTP sending, where every request costs
 * an SMS.
 */
class CambodianPhone implements ValidationRule
{
    public const TEN_DIGIT_PREFIXES = ['097', '096', '088', '071', '031', '018', '038', '076'];

    public const NINE_DIGIT_PREFIXES = [
        '010', '011', '012', '013', '014', '015', '016', '017',
        '060', '061', '066', '067', '068', '069', '070', '077',
        '078', '080', '081', '083', '084', '085', '086', '087',
        '089', '090', '092', '093', '095', '098', '099',
    ];

    /** +85512345678 / 85512345678 / 12345678 / 012-345-678 → 012345678 */
    public static function normalize(?string $raw): string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') return '';
        if (str_starts_with($d, '855')) $d = substr($d, 3);
        if (! str_starts_with($d, '0')) $d = '0' . $d;
        return $d;
    }

    public static function isValid(?string $raw): bool
    {
        $p = self::normalize($raw);
        if (strlen($p) < 3) return false;
        $prefix = substr($p, 0, 3);
        if (in_array($prefix, self::TEN_DIGIT_PREFIXES, true))  return strlen($p) === 10;
        if (in_array($prefix, self::NINE_DIGIT_PREFIXES, true)) return strlen($p) === 9;
        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') return; // `required` decides that
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('Please enter a valid Cambodian mobile number.');
        }
    }
}
