<?php

namespace App\Models;

use App\Support\Localization\AutoTranslation;
use App\Support\Localization\Phrasebook;
use App\Support\Localization\SiteLocale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Cached in place of a key that has no row. The cache cannot store null
     * (it reads back as a miss), so caching the row's null value meant every
     * unset key was queried, and written back to the cache table, on every
     * request: 91 queries and 91 writes per homepage view.
     */
    private const NOT_STORED = '__setting_not_stored__';

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Memoized for the request, since one page reads many keys more than once.
     * $default is applied after the cache, never stored in it, so two callers
     * with different defaults each get their own.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::memo()->rememberForever("setting:{$key}", fn () => static::query()->where('key', $key)->value('value') ?? self::NOT_STORED);

        return $value === self::NOT_STORED ? $default : ($value ?? $default);
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
        Cache::memo()->forget("setting:{$key}");
    }
}
