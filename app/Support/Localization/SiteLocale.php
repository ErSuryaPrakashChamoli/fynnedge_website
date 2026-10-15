<?php

namespace App\Support\Localization;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * The visitor's chosen site language (English or Hindi), picked with the
 * header's language switch and remembered in a long-lived cookie, so every
 * URL stays the same in both languages.
 *
 * Only the public site follows it. The admin panel always runs in English,
 * including the Livewire requests its forms and tables make: those share the
 * public `web` middleware group, and an admin who browsed the site in Hindi
 * would otherwise get Hindi table cells and translated model values in the
 * middle of an English panel (see appliesTo()).
 */
final class SiteLocale
{
    public const COOKIE = 'site_locale';

    public const DEFAULT = 'en';

    /**
     * Every language the site is written in, as code => its own name, so a
     * visitor who cannot read the current language still recognises theirs.
     *
     * @var array<string, string>
     */
    public const SUPPORTED = [
        'en' => 'English',
        'hi' => 'हिन्दी',
    ];

    /** One year: the choice should outlive a session, and survive a return visit. */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, self::SUPPORTED);
    }

    public static function fromRequest(Request $request): string
    {
        $locale = $request->cookies->get(self::COOKIE);

        return self::isSupported($locale) ? $locale : self::DEFAULT;
    }

    /**
     * False for the admin panel and for any Livewire request a panel page
     * makes (its original path is read from the component snapshot).
     */
    public static function appliesTo(Request $request): bool
    {
        $adminPath = trim(Filament::getPanel('admin')->getPath(), '/');
        $path = Livewire::isLivewireRequest() ? Livewire::originalPath() : $request->path();

        return ! Str::is([$adminPath, "{$adminPath}/*"], trim((string) $path, '/'));
    }

    public static function current(): string
    {
        // Without a booted application (plain unit tests) there is no locale: that is English.
        $locale = app()->bound('config') && method_exists(app(), 'getLocale') ? app()->getLocale() : self::DEFAULT;

        return self::isSupported($locale) ? $locale : self::DEFAULT;
    }

    /** Whether content should be served from its translated columns rather than the English ones. */
    public static function isTranslating(): bool
    {
        return self::current() !== self::DEFAULT;
    }

    public static function ogLocale(): string
    {
        return self::current().'_IN';
    }

    /**
     * Where the switch sends the visitor back to: the page they were on, as
     * a same-site path only. Anything else ("//evil.example", a full URL, a
     * backslash trick) falls back to the home page, so the switch can never
     * be used as an open redirect.
     */
    public static function safeReturnPath(mixed $path): string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || Str::startsWith($path, ['//', '/\\']) || preg_match('/[\x00-\x1F\\\\]/', $path)) {
            return '/';
        }

        return $path;
    }
}
