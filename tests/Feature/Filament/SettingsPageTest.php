<?php

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('renders the settings page', function () {
    $this->get('/admin/settings')->assertOk();
});

it('lets an admin save contact channel settings', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'contact_phone' => '+91 90000 00000',
            'contact_email' => 'hello@fynnedge.com',
            'contact_whatsapp' => '+91 90000 00001',
            'contact_address' => 'Plot No. 135P, Sector 44, Gurgaon-122001',
            'contact_map_url' => 'https://www.google.com/maps/embed?pb=abc123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('contact_phone'))->toBe('+91 90000 00000');
    expect(Setting::get('contact_email'))->toBe('hello@fynnedge.com');
    expect(Setting::get('contact_whatsapp'))->toBe('+91 90000 00001');
    expect(Setting::get('contact_address'))->toBe('Plot No. 135P, Sector 44, Gurgaon-122001');
    expect(Setting::get('contact_map_url'))->toBe('https://www.google.com/maps/embed?pb=abc123');
});

it('lets an admin switch the footer map off', function () {
    Livewire::test(Settings::class)
        ->assertFormSet(['footer_map_enabled' => true])
        ->fillForm(['footer_map_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('footer_map_enabled'))->toBeFalse();
});

it('lets an admin save the grievance phone and email', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'grievance_phone' => '+91-9999-000-111',
            'grievance_email' => 'grievance@fynnedge.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('grievance_phone'))->toBe('+91-9999-000-111');
    expect(Setting::get('grievance_email'))->toBe('grievance@fynnedge.com');
});

it('rejects a grievance email that is not an email address', function () {
    Livewire::test(Settings::class)
        ->fillForm(['grievance_email' => 'not an email'])
        ->call('save')
        ->assertHasFormErrors(['grievance_email' => 'email']);
});

it('lets an admin save a YouTube channel link', function () {
    Livewire::test(Settings::class)
        ->fillForm(['social_youtube' => 'https://youtube.com/@fynnedge'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('social_youtube'))->toBe('https://youtube.com/@fynnedge');
});

it('rejects a YouTube link that is not a URL', function () {
    Livewire::test(Settings::class)
        ->fillForm(['social_youtube' => 'not a url'])
        ->call('save')
        ->assertHasFormErrors(['social_youtube' => 'url']);
});

it('normalizes a plain Google Maps share link into an embeddable one on save', function () {
    Livewire::test(Settings::class)
        ->fillForm(['contact_map_url' => 'https://maps.google.com/maps?q=28.5854,77.3130&z=17'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('contact_map_url'))
        ->toBe('https://maps.google.com/maps?q=28.5854,77.3130&z=17&output=embed');
});

it('leaves an already-embeddable map URL untouched on save', function () {
    Livewire::test(Settings::class)
        ->fillForm(['contact_map_url' => 'https://www.google.com/maps/embed?pb=abc123'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('contact_map_url'))->toBe('https://www.google.com/maps/embed?pb=abc123');
});

it('keeps only the map src when an admin pastes the whole Embed a map code', function () {
    Livewire::test(Settings::class)
        ->fillForm(['contact_map_url' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18!3m3!1m2!1s0x390ce55861eb298f%3A0xd0bfe46bb90c72f8!2sFynnedge%20Advisory%20Pvt.%20Ltd.!5e0" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('contact_map_url'))
        ->toBe('https://www.google.com/maps/embed?pb=!1m18!3m3!1m2!1s0x390ce55861eb298f%3A0xd0bfe46bb90c72f8!2sFynnedge%20Advisory%20Pvt.%20Ltd.!5e0');
});

it('turns a Google Maps place link, which Google refuses to frame, into a place search map', function () {
    Livewire::test(Settings::class)
        ->fillForm(['contact_map_url' => 'https://www.google.com/maps/place/Fynnedge+Advisory+Pvt.+Ltd./@28.585606,77.31294,17z/data=!3m1!4b1!4m6!3m5!1s0x390ce55861eb298f:0xd0bfe46bb90c72f8!8m2!3d28.585606!4d77.31294!16s%2Fg%2F11zgr_b31z?entry=ttu'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('contact_map_url'))
        ->toBe('https://maps.google.com/maps?q=Fynnedge+Advisory+Pvt.+Ltd.&ll=28.585606%2C77.31294&z=17&output=embed');
});

it('follows a Share → Copy link short link to the place it points at', function () {
    Http::preventStrayRequests();
    Http::fake([
        'maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Fynnedge+Advisory+Pvt.+Ltd./@28.585606,77.31294,17z/data=!4m6!3m5!1s0x390ce55861eb298f:0xd0bfe46bb90c72f8!8m2!3d28.585606!4d77.31294!16s%2Fg%2F11zgr_b31z?entry=tts',
        ]),
    ]);

    Livewire::test(Settings::class)
        ->fillForm(['contact_map_url' => 'https://maps.app.goo.gl/nMFxPSk98pK1nzDr9'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('contact_map_url'))
        ->toBe('https://maps.google.com/maps?q=Fynnedge+Advisory+Pvt.+Ltd.&ll=28.585606%2C77.31294&z=17&output=embed');
});

it('rejects a map link that cannot be shown inside the site and keeps the saved one', function (string $mapLink) {
    Http::fake(['maps.app.goo.gl/*' => Http::failedConnection()]);
    Setting::set('contact_map_url', 'https://www.google.com/maps/embed?pb=abc123');

    Livewire::test(Settings::class)
        ->fillForm(['contact_map_url' => $mapLink])
        ->call('save')
        ->assertHasFormErrors(['contact_map_url']);

    expect(Setting::get('contact_map_url'))->toBe('https://www.google.com/maps/embed?pb=abc123');
})->with([
    'not a Google Maps link' => 'https://example.com/our-office',
    'short link while Google is unreachable' => 'https://maps.app.goo.gl/nMFxPSk98pK1nzDr9',
]);

it('lets an admin save branding, hero and footer text settings', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'site_name' => 'Acme Loans',
            'site_tagline' => 'Faster loans, fewer forms',
            'hero_eyebrow' => 'Acme Loans Pvt Ltd',
            'hero_heading' => 'Borrow smarter.',
            'hero_heading_accent' => 'Live better.',
            'hero_subheading' => 'Acme connects you with lenders across every major loan category.',
            'footer_legal_name' => 'Acme Loans Advisory Pvt Ltd',
            'footer_disclaimer' => 'All loans are subject to lender approval and underwriting.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('site_name'))->toBe('Acme Loans');
    expect(Setting::get('site_tagline'))->toBe('Faster loans, fewer forms');
    expect(Setting::get('hero_eyebrow'))->toBe('Acme Loans Pvt Ltd');
    expect(Setting::get('hero_heading'))->toBe('Borrow smarter.');
    expect(Setting::get('hero_heading_accent'))->toBe('Live better.');
    expect(Setting::get('hero_subheading'))->toBe('Acme connects you with lenders across every major loan category.');
    expect(Setting::get('footer_legal_name'))->toBe('Acme Loans Advisory Pvt Ltd');
    expect(Setting::get('footer_disclaimer'))->toBe('All loans are subject to lender approval and underwriting.');
});

it('lets an admin upload a logo, favicon and default SEO image', function () {
    Storage::fake('public');

    Livewire::test(Settings::class)
        ->fillForm([
            'site_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'site_favicon' => UploadedFile::fake()->image('favicon.png', 64, 64)->size(100),
            'seo_default_og_image' => UploadedFile::fake()->image('og.jpg', 1200, 630),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('site_logo'))->not->toBeNull();
    expect(Setting::get('site_favicon'))->not->toBeNull();
    expect(Setting::get('seo_default_og_image'))->not->toBeNull();
    Storage::disk('public')->assertExists(Setting::get('site_logo'));
});

it('rejects a website name over the character limit', function () {
    Livewire::test(Settings::class)
        ->fillForm(['site_name' => str_repeat('a', 61)])
        ->call('save')
        ->assertHasFormErrors(['site_name']);
});

it('rejects an oversized logo upload', function () {
    Storage::fake('public');

    Livewire::test(Settings::class)
        ->fillForm(['site_logo' => UploadedFile::fake()->image('logo.png')->size(3000)])
        ->call('save')
        ->assertHasFormErrors(['site_logo']);
});

it('rejects a non-image file type for the logo upload', function () {
    Storage::fake('public');

    Livewire::test(Settings::class)
        ->fillForm(['site_logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
        ->call('save')
        ->assertHasFormErrors(['site_logo']);
});

it('lets an admin save header, footer and main content appearance settings', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'theme_header_bg_color' => '#112233',
            'theme_header_font_color' => '#aabbcc',
            'theme_footer_font_size' => '20px',
            'theme_footer_font_weight' => '700',
            'theme_main_font_style' => 'italic',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('theme_header_bg_color'))->toBe('#112233');
    expect(Setting::get('theme_header_font_color'))->toBe('#aabbcc');
    expect(Setting::get('theme_footer_font_size'))->toBe('20px');
    // A purely-numeric Select option key ('700') comes back from PHP as an
    // int, not the original string — PHP casts any canonical-integer array
    // key to int. SiteThemeStyles normalizes this back to string itself.
    expect(Setting::get('theme_footer_font_weight'))->toBe(700);
    expect(Setting::get('theme_main_font_style'))->toBe('italic');
});

it('lets an admin save the new link colour and font family on a section', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'theme_main_link_color' => '#c62828',
            'theme_header_font_family' => 'display',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('theme_main_link_color'))->toBe('#c62828');
    expect(Setting::get('theme_header_font_family'))->toBe('display');
});

it('lets an admin save the homepage banner appearance settings', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'theme_banner_font_color' => '#ffdd00',
            'theme_banner_font_family' => 'mono',
            'theme_banner_font_size' => '40px',
            'theme_banner_font_style' => 'italic',
            'theme_banner_button_color' => '#c62828',
            'theme_banner_button_hover_color' => '#8e0000',
            'theme_banner_button_text_color' => '#ffffff',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('theme_banner_font_color'))->toBe('#ffdd00');
    expect(Setting::get('theme_banner_font_family'))->toBe('mono');
    expect(Setting::get('theme_banner_font_size'))->toBe('40px');
    expect(Setting::get('theme_banner_button_color'))->toBe('#c62828');
    expect(Setting::get('theme_banner_button_hover_color'))->toBe('#8e0000');
});
