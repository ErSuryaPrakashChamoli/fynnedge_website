<?php

use App\Models\Setting;
use App\Support\Theme\SiteThemeStyles;

it('renders nothing when no appearance settings are configured', function () {
    expect(SiteThemeStyles::render())->toBe('');
});

it('renders a scoped override per section for each configured field', function () {
    Setting::set('theme_header_bg_color', '#112233');
    Setting::set('theme_header_font_color', '#aabbcc');
    Setting::set('theme_footer_font_size', '20px');
    Setting::set('theme_main_font_weight', '700');
    Setting::set('theme_main_font_style', 'italic');

    $html = SiteThemeStyles::render();

    expect($html)
        ->toContain('<style>')
        ->toContain('header{--color-bg:#112233;--color-ink:#aabbcc;}')
        ->toContain('footer{--text-xs:20px;')
        ->toContain('#main-content{')
        ->toContain('--font-weight-bold:700;')
        ->toContain('font-style:italic;');
});

it('accepts a font weight stored as an int, since a numeric Select option key comes back as int', function () {
    Setting::set('theme_footer_font_weight', 700);

    $html = SiteThemeStyles::render();

    expect($html)->toStartWith('<style>footer{')
        ->and($html)->toContain('--font-weight-bold:700;');
});

it('ignores a value that bypasses the Filament form and is not a valid hex colour', function () {
    Setting::set('theme_header_bg_color', 'javascript:alert(1)');
    Setting::set('theme_header_font_color', '</style><script>alert(1)</script>');

    expect(SiteThemeStyles::render())->toBe('');
});

it('ignores a font size, weight or style value outside the allowed list', function () {
    Setting::set('theme_footer_font_size', '999px; } body { display:none');
    Setting::set('theme_footer_font_weight', '1000');
    Setting::set('theme_footer_font_style', 'oblique');

    expect(SiteThemeStyles::render())->toBe('');
});
