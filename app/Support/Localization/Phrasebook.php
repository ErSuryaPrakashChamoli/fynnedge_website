<?php

namespace App\Support\Localization;

/**
 * Translates admin-editable copy that lives in Settings rather than in a
 * model column (site tagline, hero text, the About / Quick Enquiry /
 * Calculator / Credit Score page copy).
 *
 * Settings hold English. While the site runs in Hindi, a value resolves in
 * this order:
 *   1. the admin's own Hindi wording, saved beside it (`{key}_hi`)
 *   2. the English text looked up in lang/hi.json. Every built-in default is
 *      in there, so a page nobody has reworded is fully Hindi.
 *   3. the English text as written, for English an admin reworded and has
 *      not translated yet.
 * In English every method returns the English value untouched.
 */
final class Phrasebook
{
    /**
     * Step 2 and 3 only: each string, at any depth, swapped for its Hindi when
     * lang/hi.json has it. Array keys and non-string values are left alone.
     */
    public static function translate(mixed $value): mixed
    {
        if (! SiteLocale::isTranslating()) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(self::translate(...), $value);
        }

        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $translated = __($value);

        return is_string($translated) ? $translated : $value;
    }

    /**
     * A resolved content array (one Setting holding a whole page's copy) in
     * the visitor's language. A field the admin translated wins; a list is
     * taken whole from the Hindi copy when it has any items, since its items
     * need not line up one-to-one with the English ones.
     *
     * @param  array<string, mixed>  $english
     * @return array<string, mixed>
     */
    public static function localize(array $english, mixed $hindi): array
    {
        if (! SiteLocale::isTranslating()) {
            return $english;
        }

        $hindi = is_array($hindi) ? $hindi : [];

        foreach ($english as $key => $value) {
            $override = $hindi[$key] ?? null;

            $english[$key] = match (true) {
                is_array($value) && is_array($override) && self::hasItems($override) => array_values(array_filter($override, fn (mixed $item): bool => self::hasItems($item))),
                is_string($value) && is_string($override) && filled($override) => trim($override),
                default => self::translate($value),
            };
        }

        return $english;
    }

    private static function hasItems(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn (mixed $item): bool => self::hasItems($item));
        }

        return filled($value);
    }
}
