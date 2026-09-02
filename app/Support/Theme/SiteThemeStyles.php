<?php

namespace App\Support\Theme;

use App\Models\Setting;

/**
 * Renders a scoped <style> block that overrides the header/footer/main-content
 * background, font colour, font size, font weight and font style from
 * admin-configured Settings (see Filament\Pages\Settings "Appearance" section).
 *
 * Every value is re-validated against a closed allow-list (or a strict hex
 * regex) here, never trusted from Setting::get() alone — this string is
 * echoed unescaped into the page <head>, so a stray value must never reach
 * output even if it somehow bypassed the Filament form's own constraints.
 *
 * Overriding these as CSS custom properties, scoped to each section's root
 * element, works because every Tailwind utility in this app already resolves
 * through the same variables (e.g. .text-ink { color: var(--color-ink) }) —
 * redeclaring them on <header>/<footer>/#main-content cascades to every
 * descendant utility without touching a single Blade template. See
 * .ai/rules/views.md for the CSS-variable theming convention this builds on.
 */
class SiteThemeStyles
{
    /**
     * @var array<string, array{selector: string, bgVar: string}>
     */
    private const SECTIONS = [
        'header' => ['selector' => 'header', 'bgVar' => '--color-bg'],
        'footer' => ['selector' => 'footer', 'bgVar' => '--color-surface'],
        'main' => ['selector' => '#main-content', 'bgVar' => '--color-bg'],
    ];

    private const SIZE_VARS = [
        '--text-xs', '--text-sm', '--text-base', '--text-lg', '--text-xl',
        '--text-2xl', '--text-3xl', '--text-4xl', '--text-5xl', '--text-6xl',
    ];

    private const WEIGHT_VARS = [
        '--font-weight-thin', '--font-weight-extralight', '--font-weight-light',
        '--font-weight-normal', '--font-weight-medium', '--font-weight-semibold',
        '--font-weight-bold', '--font-weight-extrabold', '--font-weight-black',
    ];

    public const FONT_SIZES = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'];

    public const FONT_WEIGHTS = ['300', '400', '500', '600', '700', '800'];

    public const FONT_STYLES = ['normal', 'italic'];

    public static function render(): string
    {
        $blocks = [];

        foreach (self::SECTIONS as $key => $config) {
            $declarations = self::declarationsFor($key, $config['bgVar']);

            if ($declarations !== []) {
                $blocks[] = $config['selector'].'{'.implode('', $declarations).'}';
            }
        }

        return $blocks === [] ? '' : '<style>'.implode('', $blocks).'</style>';
    }

    /**
     * @return array<int, string>
     */
    private static function declarationsFor(string $key, string $bgVar): array
    {
        $declarations = [];

        if ($bg = self::hexColor(Setting::get("theme_{$key}_bg_color"))) {
            $declarations[] = "{$bgVar}:{$bg};";
        }

        if ($fontColor = self::hexColor(Setting::get("theme_{$key}_font_color"))) {
            $declarations[] = "--color-ink:{$fontColor};";
        }

        if ($size = self::allowed(Setting::get("theme_{$key}_font_size"), self::FONT_SIZES)) {
            foreach (self::SIZE_VARS as $var) {
                $declarations[] = "{$var}:{$size};";
            }
        }

        if ($weight = self::allowed(Setting::get("theme_{$key}_font_weight"), self::FONT_WEIGHTS)) {
            foreach (self::WEIGHT_VARS as $var) {
                $declarations[] = "{$var}:{$weight};";
            }
        }

        if ($style = self::allowed(Setting::get("theme_{$key}_font_style"), self::FONT_STYLES)) {
            $declarations[] = "font-style:{$style};";
        }

        return $declarations;
    }

    private static function hexColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
    }

    /**
     * A purely-numeric option key (e.g. the font-weight options '300'..'800')
     * comes back from Filament/PHP as an int, not the original string — PHP
     * silently casts any canonical-integer array key to int, and Setting's
     * array cast preserves whatever type was saved. Normalize to string
     * before checking against the allow-list so this isn't rejected.
     *
     * @param  array<int, string>  $allowed
     */
    private static function allowed(mixed $value, array $allowed): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = (string) $value;

        return in_array($value, $allowed, true) ? $value : null;
    }
}
