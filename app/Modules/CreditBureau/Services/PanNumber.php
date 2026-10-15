<?php

namespace App\Modules\CreditBureau\Services;

/**
 * The one definition of a PAN for credit bureau requests: trimmed, inner spaces
 * removed and upper-cased BEFORE validation, hashing or storage, so "abcde 1234f"
 * and "ABCDE1234F" are the same customer to every duplicate check.
 *
 * The hash is an HMAC keyed from APP_KEY rather than a bare SHA-256: a bare
 * hash of a 10-character PAN can be reversed by brute force, an HMAC cannot
 * without the key. Rotating APP_KEY therefore changes every hash — see
 * docs/crif-credit-report-integration.md before rotating it.
 */
final class PanNumber
{
    /** Five letters, four digits, one letter: ABCDE1234F. */
    public const PATTERN = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';

    public static function normalize(mixed $value): string
    {
        $pan = is_scalar($value) ? (string) $value : '';

        return strtoupper(preg_replace('/\s+/', '', $pan) ?? '');
    }

    public static function isValid(string $pan): bool
    {
        return preg_match(self::PATTERN, $pan) === 1;
    }

    public static function hash(string $normalizedPan): string
    {
        return hash_hmac('sha256', $normalizedPan, 'crif-pan|'.config('app.key'));
    }

    /** "ABCDE1234F" → "XXXXXX234F". Shown in lists, logs and audit rows instead of the PAN. */
    public static function mask(string $pan): string
    {
        return str_repeat('X', max(strlen($pan) - 4, 0)).substr($pan, -4);
    }
}
