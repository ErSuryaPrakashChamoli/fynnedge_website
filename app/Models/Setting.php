<?php

namespace App\Models;

use App\Enums\ContentCacheSection;
use App\Models\Concerns\InvalidatesContentCache;
use App\Support\ContentCache\ContentCache;
use App\Support\Localization\AutoTranslation;
use App\Support\Localization\Phrasebook;
use App\Support\Localization\SiteLocale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use InvalidatesContentCache;

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Every setting is read in one query and cached as one key => value map.
     * The table is small (under a hundred rows, ~2KB) and a page reads ~90
     * keys, which as one cache lookup per key cost ~90 round trips a page.
     * A key with no row is simply absent from the map, so it needs no
     * placeholder. $default is applied after the cache, never stored in it,
     * so two callers with different defaults each get their own.
     *
     * Any saved or deleted Setting invalidates the map (InvalidatesContentCache).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::cachedValues()[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cachedValues(): array
    {
        return ContentCache::remember('settings', [ContentCacheSection::Settings], fn (): array => static::query()->pluck('value', 'key')->all());
    }

    /**
     * A visitor-facing text Setting in the visitor's language: the admin's
     * Hindi wording saved as `{key}_hi` when there is one, otherwise the
     * English value through the Phrasebook (see that class for the order).
     */
    public static function localized(string $key, mixed $default = null): mixed
    {
        $value = static::get($key, $default);

        if (! SiteLocale::isTranslating()) {
            return $value;
        }

        $translated = static::get($key.'_'.SiteLocale::current());

        return filled($translated) ? $translated : Phrasebook::translate($value);
    }

    public static function set(string $key, mixed $value): void
    {
        AutoTranslation::settingSaving($key);

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
