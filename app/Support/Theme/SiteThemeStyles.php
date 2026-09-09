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

    public const BANNER_FONT_SIZES = ['20px', '24px', '28px', '32px', '36px', '40px', '48px', '56px'];

    public const FONT_WEIGHTS = ['300', '400', '500', '600', '700', '800'];

    public const FONT_STYLES = ['normal', 'italic'];

    /**
     * Admins pick a key; the CSS stack it maps to is our own constant and
     * never admin input, so nothing user-supplied reaches the font-family
     * declaration. Keys mirror the three families declared in app.css.
     *
     * @var array<string, string>
     */
    public const FONT_FAMILIES = [
        'sans' => "'IBM Plex Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif",
        'display' => "'Fraunces', ui-serif, Georgia, 'Times New Roman', serif",
        'mono' => "'IBM Plex Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace",
    ];

    public static function render(): string
    {
        $blocks = [];

        foreach (self::SECTIONS as $key => $config) {
            $declarations = self::declarationsFor($key, $config['bgVar']);

            if ($declarations !== []) {
                $blocks[] = $config['selector'].'{'.implode('', $declarations).'}';
            }
        }

        $blocks = [...$blocks, ...self::bannerBlocks()];

        return $blocks === [] ? '' : '<style>'.implode('', $blocks).'</style>';
    }

    /**
     * The homepage banner is styled through its own custom properties rather
     * than the generic `--color-ink` override the other sections use.
     *
     * Its text sits on a photograph behind a dark scrim, so its readable
     * default is white — not the global ink colour.
     *
     * Nothing is emitted until an admin actually configures something: the
     * unstyled defaults already live in the markup as utility classes
     * (`text-white`, and `variant="primary"`'s `bg-accent` /
     * `hover:bg-accent-strong`, which is what makes the CTA match every other
     * button on the site). Once any one field is set, the whole block is
     * emitted so the untouched values still resolve to those same defaults.
     *
     * @return array<int, string>
     */
    private static function bannerBlocks(): array
    {
        $configured = false;

        $root = [
            '--banner-text-color:#ffffff;',
            '--banner-muted-color:rgba(255,255,255,0.85);',
            '--banner-button-bg:var(--color-accent);',
            '--banner-button-bg-hover:var(--color-accent-strong);',
            '--banner-button-text:#ffffff;',
        ];

        if ($color = self::hexColor(Setting::get('theme_banner_font_color'))) {
            $root[0] = "--banner-text-color:{$color};";
            $root[1] = "--banner-muted-color:{$color};";
            $configured = true;
        }

        if ($buttonBg = self::hexColor(Setting::get('theme_banner_button_color'))) {
            $root[2] = "--banner-button-bg:{$buttonBg};";
            $root[3] = "--banner-button-bg-hover:{$buttonBg};";
            $configured = true;
        }

        if ($buttonHover = self::hexColor(Setting::get('theme_banner_button_hover_color'))) {
            $root[3] = "--banner-button-bg-hover:{$buttonHover};";
            $configured = true;
        }

        if ($buttonText = self::hexColor(Setting::get('theme_banner_button_text_color'))) {
            $root[4] = "--banner-button-text:{$buttonText};";
            $configured = true;
        }

        $heading = [];
        $shared = [];

        if ($family = self::allowed(Setting::get('theme_banner_font_family'), array_keys(self::FONT_FAMILIES))) {
            $shared[] = 'font-family:'.self::FONT_FAMILIES[$family].';';
        }

        if ($style = self::allowed(Setting::get('theme_banner_font_style'), self::FONT_STYLES)) {
            $shared[] = "font-style:{$style};";
        }

        if ($size = self::allowed(Setting::get('theme_banner_font_size'), self::BANNER_FONT_SIZES)) {
            $heading[] = "font-size:{$size};";
        }

        if ($weight = self::allowed(Setting::get('theme_banner_font_weight'), self::FONT_WEIGHTS)) {
            $heading[] = "font-weight:{$weight};";
        }

        if (! $configured && $shared === [] && $heading === []) {
            return [];
        }

        return [
            '#hero-banner{'.implode('', $root).'}',
            '#hero-banner .banner-heading{color:var(--banner-text-color);'.implode('', [...$shared, ...$heading]).'}',
            '#hero-banner .banner-subtitle{color:var(--banner-muted-color);'.implode('', $shared).'}',
            '#hero-banner .banner-cta{background-color:var(--banner-button-bg);color:var(--banner-button-text);}',
            '#hero-banner .banner-cta:hover{background-color:var(--banner-button-bg-hover);}',
        ];
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

        /**
         * Links, and anything else accent-coloured in this section, resolve
         * through --color-accent — so overriding it here covers "text or
         * link" without needing a second mechanism. It also recolours accent
         * buttons inside the section, which is the intended reading of "it
         * applies to everything".
         */
        if ($linkColor = self::hexColor(Setting::get("theme_{$key}_link_color"))) {
            $declarations[] = "--color-accent:{$linkColor};";
            $declarations[] = "--color-accent-strong:{$linkColor};";
        }

        /**
         * Forces every family tier in the section to one stack, the same
         * deliberate uniform-override trade-off already documented for font
         * size and weight.
         */
        if ($family = self::allowed(Setting::get("theme_{$key}_font_family"), array_keys(self::FONT_FAMILIES))) {
            foreach (['--font-sans', '--font-display', '--font-mono'] as $var) {
                $declarations[] = "{$var}:".self::FONT_FAMILIES[$family].';';
            }
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
