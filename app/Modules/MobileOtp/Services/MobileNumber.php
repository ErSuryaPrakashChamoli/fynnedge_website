<?php

namespace App\Modules\MobileOtp\Services;

/**
 * The one definition of an Indian mobile number on this site: stored as ten
 * bare digits starting 6-9, sent to SMS gateways with the 91 country code.
 *
 * normalize() must run BEFORE validation everywhere, or the PATTERN rule
 * judges a different string from the one that is stored and texted.
 */
class MobileNumber
{
    /** Ten digits, starting 6-9 — the rule every mobile-number form uses. */
    public const PATTERN = '/^[6-9]\d{9}$/';

    /** ISO 3166-1 alpha-2 codes accepted by the API's country_code field. */
    public const SUPPORTED_COUNTRIES = ['IN'];

    private const DIALLING_CODE = '91';

    /**
     * Strips separators and a +91 / 91 / 0 prefix: "+91 98765-43210" → "9876543210".
     * Anything that is not a scalar becomes "" so the `required` rule rejects it.
     */
    public static function normalize(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', is_scalar($value) ? (string) $value : '') ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, self::DIALLING_CODE)) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    public static function isValid(string $number): bool
    {
        return preg_match(self::PATTERN, $number) === 1;
    }

    /** "9876543210" → "919876543210", the recipient format SMS gateways expect. */
    public static function international(string $number): string
    {
        return self::DIALLING_CODE.$number;
    }

    /** "9876543210" → "******3210". Shown to visitors, admins and logs instead of the number. */
    public static function mask(string $number): string
    {
        return str_repeat('*', max(strlen($number) - 4, 0)).substr($number, -4);
    }
}
