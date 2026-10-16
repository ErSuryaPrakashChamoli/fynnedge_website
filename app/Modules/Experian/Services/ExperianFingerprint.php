<?php

namespace App\Modules\Experian\Services;

use App\Modules\CreditBureau\Services\PanNumber;

/**
 * Keys that identify an Experian request without storing its details in the
 * clear — HMACs keyed from APP_KEY (rotating it changes every key; see
 * docs/experian-credit-bureau-integration.md).
 */
final class ExperianFingerprint
{
    /**
     * "The same details as sent": stops an identical request from being paid for
     * again after Surepass rejected it, while corrected details stay allowed.
     */
    public static function request(string $pan, string $name, string $mobile): string
    {
        $normalised = implode('|', [
            PanNumber::normalize($pan),
            mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? '')),
            preg_replace('/\D+/', '', $mobile) ?? '',
        ]);

        return hash_hmac('sha256', $normalised, 'experian-request|'.config('app.key'));
    }

    /**
     * The in-flight lock value: one unresolved request per PAN per product.
     */
    public static function inFlight(string $panHash, string $product): string
    {
        return hash_hmac('sha256', $product.'|'.$panHash, 'experian-in-flight|'.config('app.key'));
    }
}
