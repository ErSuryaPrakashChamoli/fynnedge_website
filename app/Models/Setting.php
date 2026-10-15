<?php

namespace App\Models;

use App\Support\Localization\Phrasebook;
use App\Support\Localization\SiteLocale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", fn () => static::query()->where('key', $key)->value('value') ?? $default);
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
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }
}
