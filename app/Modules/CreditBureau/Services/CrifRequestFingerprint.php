<?php

namespace App\Modules\CreditBureau\Services;

/**
 * Identifies "the same request details" without storing them in the clear:
 * an HMAC over the normalised PAN, name and mobile. Used to stop an identical
 * request from being re-sent after Surepass rejected it, while still letting an
 * employee try again with corrected details.
 */
final class CrifRequestFingerprint
{
    public static function make(string $pan, string $firstName, string $lastName, string $mobile): string
    {
        $normalised = implode('|', [
            PanNumber::normalize($pan),
            mb_strtolower(trim(preg_replace('/\s+/', ' ', $firstName) ?? '')),
            mb_strtolower(trim(preg_replace('/\s+/', ' ', $lastName) ?? '')),
            preg_replace('/\D+/', '', $mobile) ?? '',
        ]);

        return hash_hmac('sha256', $normalised, 'crif-request|'.config('app.key'));
    }
}
