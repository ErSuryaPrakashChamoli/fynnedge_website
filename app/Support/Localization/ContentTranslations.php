<?php

namespace App\Support\Localization;

use App\Support\ContentCache\ContentCache;
use App\Support\Rates\RateBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Fills the Hindi columns (`{column}_hi`) of existing content from the
 * translation files in database/seeders/data/translations/{locale}/{table}.json.
 *
 * Each file is a list of {"field", "en", "hi"} entries. An entry is matched to
 * rows by the exact English text it was translated from, never by id or slug,
 * so the same file is safe on every database: a row whose English an admin
 * has since reworded keeps falling back to English rather than getting a
 * translation of words it no longer says. Rows that already have a Hindi
 * value are never touched, so an editor's own translation always survives a
 * re-run.
 *
 * Writes go through the query builder on purpose: this is a bulk backfill, and
 * hundreds of model saves would each write an audit-log row. That also skips
 * the model hooks that clear RateBook's cache, so apply() clears it itself.
 */
final class ContentTranslations
{
    /**
     * @param  string|null  $directory  the folder of {table}.json files; defaults to the locale's shipped translations
     * @return array<string, int> table => number of Hindi values written
     */
    public static function apply(string $locale = 'hi', ?string $directory = null): array
    {
        $written = [];
        $directory ??= database_path("seeders/data/translations/{$locale}");

        foreach (File::glob("{$directory}/*.json") as $file) {
            $table = basename($file, '.json');

            if (! Schema::hasTable($table)) {
                continue;
            }

            $written[$table] = 0;

            foreach (self::index($file) as $field => $translations) {
                $column = "{$field}_{$locale}";

                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->select(['id', $field])
                    ->where(fn ($query) => $query->whereNull($column)->orWhere($column, ''))
                    ->whereNotNull($field)
                    ->chunkById(200, function ($rows) use ($table, $field, $column, $translations, &$written): void {
                        foreach ($rows as $row) {
                            $translation = $translations[self::fingerprint(self::decoded($row->{$field}))] ?? null;

                            if ($translation === null) {
                                continue;
                            }

                            DB::table($table)->where('id', $row->id)->update([
                                $column => is_array($translation)
                                    ? json_encode($translation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                    : $translation,
                            ]);

                            $written[$table]++;
                        }
                    });
            }
        }

        // These writes skip the model hooks that normally invalidate the cached
        // copies of this text (the mega menu's Hindi labels, RateBook's rows).
        foreach (array_keys(array_filter($written)) as $table) {
            ContentCache::invalidateTable($table);
        }

        // RateBook additionally forgets this process's memo of its rows.
        if (array_sum($written) > 0) {
            RateBook::flush();
        }

        return $written;
    }

    /**
     * The key an English value is matched by. A json column compares as its
     * decoded value, so key spacing or escaping in the stored JSON never
     * stops a match; surrounding whitespace is ignored for text.
     */
    public static function fingerprint(mixed $english): string
    {
        return sha1(is_array($english)
            ? json_encode($english, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : trim((string) $english));
    }

    /**
     * @return array<string, array<string, mixed>> field => [fingerprint => translation]
     */
    private static function index(string $file): array
    {
        $index = [];

        foreach (json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR) as $entry) {
            if (blank($entry['hi'] ?? null)) {
                continue;
            }

            $index[$entry['field']][self::fingerprint($entry['en'])] = $entry['hi'];
        }

        return $index;
    }

    private static function decoded(mixed $value): mixed
    {
        if (! is_string($value) || ! in_array($value[0] ?? '', ['[', '{'], true)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
