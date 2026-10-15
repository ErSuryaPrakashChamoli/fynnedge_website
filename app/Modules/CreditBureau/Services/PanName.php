<?php

namespace App\Modules\CreditBureau\Services;

use Illuminate\Support\Str;

/**
 * The one spelling of a name "as per PAN": spaces squeezed, the first letter
 * of every word upper-cased and the rest lower-cased, however it was typed —
 * "SURYA prakash   chamoli" is saved and sent to the bureau as "Surya Prakash
 * Chamoli". Initials and joined names follow the same rule, so "s.p. d'souza-rao"
 * becomes "S.P. D'Souza-Rao". Scripts without letter case (Hindi) pass through.
 *
 * formatPanName() in resources/js/app.js applies the same rule while the
 * visitor types; this is the copy that decides what is stored.
 */
final class PanName
{
    public static function normalize(mixed $value): string
    {
        $name = mb_strtolower(Str::squish(is_scalar($value) ? (string) $value : ''));

        return preg_replace_callback(
            "/(^|[\s.'’-])(\p{Ll})/u",
            fn (array $match): string => $match[1].mb_strtoupper($match[2]),
            $name,
        ) ?? $name;
    }
}
