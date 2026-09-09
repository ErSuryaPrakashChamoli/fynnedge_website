<?php

use App\Enums\PublishStatus;
use App\Models\Banner;
use App\Models\Setting;
use App\Support\Theme\SiteThemeStyles;

/**
 * The banner's appearance is admin-controlled through banner-scoped custom
 * properties rather than the `--color-ink` override the other sections use,
 * because its text sits on a photo and must default to white, not to the
 * global ink colour.
 */
function publishedBanner(): Banner
{
    return Banner::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => now()->subMinute(),
        'heading' => 'Instant Personal Loan',
        'subtitle' => 'Quick, reliable, secure',
        'cta_label' => 'Apply Now',
        'cta_url' => '/eligibility',
    ]);
}

/**
 * With nothing configured the banner must emit no CSS at all — its defaults
 * are the utility classes already in the markup. Emitting a permanent block
 * would also break the "renders nothing when unconfigured" contract the rest
 * of this suite relies on.
 */
it('emits no banner CSS until an admin configures something', function () {
    expect(SiteThemeStyles::render())->toBe('');
});

it('falls back to the site accent for anything the admin left blank', function () {
    Setting::set('theme_banner_font_color', '#ffdd00');

    $css = SiteThemeStyles::render();

    expect($css)->toContain('--banner-button-bg:var(--color-accent);');
    expect($css)->toContain('--banner-button-bg-hover:var(--color-accent-strong);');
});

it('renders the banner CTA with the same primary button classes as the rest of the site', function () {
    publishedBanner();

    $this->get('/')
        ->assertOk()
        ->assertSee('banner-cta', false)
        ->assertSee('bg-accent', false)
        ->assertSee('hover:bg-accent-strong', false);
});

it('scopes the themeable hooks to the banner', function () {
    publishedBanner();

    $this->get('/')
        ->assertOk()
        ->assertSee('id="hero-banner"', false)
        ->assertSee('banner-heading', false)
        ->assertSee('banner-subtitle', false);
});

it('lets an admin override the banner text and button colours', function () {
    Setting::set('theme_banner_font_color', '#ffdd00');
    Setting::set('theme_banner_button_color', '#c62828');
    Setting::set('theme_banner_button_hover_color', '#8e0000');
    Setting::set('theme_banner_button_text_color', '#111111');

    $css = SiteThemeStyles::render();

    expect($css)->toContain('--banner-text-color:#ffdd00;');
    expect($css)->toContain('--banner-button-bg:#c62828;');
    expect($css)->toContain('--banner-button-bg-hover:#8e0000;');
    expect($css)->toContain('--banner-button-text:#111111;');
});

it('lets an admin override the banner font, size, weight and style', function () {
    Setting::set('theme_banner_font_family', 'mono');
    Setting::set('theme_banner_font_size', '40px');
    Setting::set('theme_banner_font_weight', '800');
    Setting::set('theme_banner_font_style', 'italic');

    $css = SiteThemeStyles::render();

    expect($css)->toContain('IBM Plex Mono');
    expect($css)->toContain('font-size:40px;');
    expect($css)->toContain('font-weight:800;');
    expect($css)->toContain('font-style:italic;');
});

it('applies the button hover colour on its own when only the base colour is set', function () {
    Setting::set('theme_banner_button_color', '#c62828');

    expect(SiteThemeStyles::render())->toContain('--banner-button-bg-hover:#c62828;');
});

/**
 * This string is echoed unescaped into <head>, so the allow-list is the only
 * thing standing between an admin field and arbitrary CSS.
 */
it('rejects every banner appearance value that is not a valid colour or allow-listed option', function () {
    Setting::set('theme_banner_font_color', 'red;} body{display:none} .x{a:b');
    Setting::set('theme_banner_font_family', '../../evil');
    Setting::set('theme_banner_font_size', '40px;background:url(javascript:alert(1))');
    Setting::set('theme_banner_font_weight', '900;position:fixed');
    Setting::set('theme_banner_button_color', '#GGGGGG');

    $css = SiteThemeStyles::render();

    expect($css)->not->toContain('body{display:none');
    expect($css)->not->toContain('javascript:');
    expect($css)->not->toContain('evil');
    expect($css)->not->toContain('position:fixed');
    expect($css)->not->toContain('#GGGGGG');

    // Every value was rejected, so nothing counts as configured and no CSS is
    // emitted at all — the markup's own defaults still render the banner.
    expect($css)->toBe('');
});

it('still emits the safe defaults when one field is valid and another is malicious', function () {
    Setting::set('theme_banner_font_color', '#ffdd00');
    Setting::set('theme_banner_button_color', 'red;} body{display:none} .x{a:b');

    $css = SiteThemeStyles::render();

    expect($css)->not->toContain('body{display:none');
    expect($css)->toContain('--banner-text-color:#ffdd00;');
    expect($css)->toContain('--banner-button-bg:var(--color-accent);');
});

it('lets an admin recolour links and accents in a page section', function () {
    Setting::set('theme_main_link_color', '#c62828');

    $css = SiteThemeStyles::render();

    expect($css)->toContain('--color-accent:#c62828;');
    expect($css)->toContain('--color-accent-strong:#c62828;');
});

it('lets an admin change a section font family', function () {
    Setting::set('theme_header_font_family', 'display');

    expect(SiteThemeStyles::render())->toContain('Fraunces');
});

it('rejects a section link colour and font family that are not allow-listed', function () {
    Setting::set('theme_main_link_color', 'blue;} html{display:none');
    Setting::set('theme_header_font_family', 'evil-font');

    $css = SiteThemeStyles::render();

    expect($css)->not->toContain('html{display:none');
    expect($css)->not->toContain('evil-font');
});
