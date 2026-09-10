<?php

use App\Filament\Pages\SeoAnalytics;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('renders the SEO & analytics page for a super admin', function () {
    $this->get('/admin/seo-analytics')->assertOk();
});

it('saves the indexing, analytics and verification settings', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm([
            'seo_indexing_enabled' => false,
            'google_search_console_verification' => 'abcDEF123456_ghiJKL-789',
            'google_analytics_enabled' => true,
            'google_analytics_measurement_id' => 'G-ABCD123456',
            'google_tag_manager_enabled' => true,
            'google_tag_manager_container_id' => 'GTM-ABC1234',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('seo_indexing_enabled'))->toBeFalse()
        ->and(Setting::get('google_analytics_measurement_id'))->toBe('G-ABCD123456')
        ->and(Setting::get('google_tag_manager_container_id'))->toBe('GTM-ABC1234')
        ->and(Setting::get('google_search_console_verification'))->toBe('abcDEF123456_ghiJKL-789');
});

it('stores only the token when an admin pastes the whole verification meta tag', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm(['google_search_console_verification' => '<meta name="google-site-verification" content="abcDEF123456_ghiJKL-789" />'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('google_search_console_verification'))->toBe('abcDEF123456_ghiJKL-789');
});

it('rejects a measurement ID or container ID in the wrong format', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm([
            'google_analytics_enabled' => true,
            'google_analytics_measurement_id' => 'UA-12345-1',
            'google_tag_manager_enabled' => true,
            'google_tag_manager_container_id' => 'GTM',
        ])
        ->call('save')
        ->assertHasFormErrors(['google_analytics_measurement_id', 'google_tag_manager_container_id']);
});

it('requires an ID once a tracker is enabled', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm([
            'google_analytics_enabled' => true,
            'google_analytics_measurement_id' => '',
            'google_tag_manager_enabled' => true,
            'google_tag_manager_container_id' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['google_analytics_measurement_id', 'google_tag_manager_container_id']);
});

it('lets a super admin save custom tracking scripts', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm([
            'custom_head_scripts' => '<script src="https://connect.facebook.net/en_US/fbevents.js"></script>',
            'custom_body_end_scripts' => '<script>window.clarity=1;</script>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('custom_head_scripts'))->toContain('fbevents.js')
        ->and(Setting::get('custom_body_end_scripts'))->toContain('window.clarity=1;');
});

it('rejects PHP tags and a stray closing body tag in a custom script', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm([
            'custom_head_scripts' => '<?php echo "x"; ?>',
            'custom_body_end_scripts' => '<script>x</script></body>',
        ])
        ->call('save')
        ->assertHasFormErrors(['custom_head_scripts', 'custom_body_end_scripts']);
});

it('keeps the page out of reach of an admin without the permission', function () {
    $editor = User::factory()->create(['is_admin' => true]);
    $editor->syncRoles([]);

    $this->actingAs($editor);

    expect(SeoAnalytics::canAccess())->toBeFalse();
    $this->get('/admin/seo-analytics')->assertForbidden();
});

it('hides the custom script fields from a permitted admin who is not a super admin', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles([]);
    $seo->givePermissionTo(Permission::findOrCreate('View:SeoAnalytics'));

    Setting::set('custom_head_scripts', '<script>original();</script>');

    $this->actingAs($seo);

    expect(SeoAnalytics::canAccess())->toBeTrue()
        ->and(SeoAnalytics::canEditCustomScripts())->toBeFalse();

    Livewire::test(SeoAnalytics::class)
        ->assertFormFieldHidden('custom_head_scripts')
        ->fillForm(['google_analytics_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    // Saving without the section must not blank the scripts it never showed them.
    expect(Setting::get('custom_head_scripts'))->toBe('<script>original();</script>');
});

it('saves the SEO defaults, verification, crawler and consent settings', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm([
            'seo_meta_description' => 'Compare lenders in minutes.',
            'seo_default_og_title' => 'FynnEdge — loans, simplified',
            'seo_twitter_card' => 'summary',
            'seo_canonical_base_url' => 'https://fynnedge.com',
            'bing_webmaster_verification' => 'ABCDEF1234567890',
            'facebook_domain_verification' => 'abcdefghij1234567890',
            'pinterest_verification' => 'zyxwvutsrq0987654321',
            'other_verification_meta' => '<meta name="yandex-verification" content="abc123">',
            'microsoft_clarity_project_id' => 'abcd12345',
            'meta_pixel_id' => '123456789012345',
            'linkedin_partner_id' => '1234567',
            'google_ads_conversion_id' => 'AW-123456789',
            'robots_ai_crawlers_allowed' => false,
            'robots_extra_directives' => 'Disallow: /private-campaign',
            'cookie_consent_enabled' => true,
            'analytics_consent_required' => true,
            'marketing_consent_required' => true,
            'privacy_policy_url' => 'https://fynnedge.com/privacy-policy',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('seo_meta_description'))->toBe('Compare lenders in minutes.')
        ->and(Setting::get('bing_webmaster_verification'))->toBe('ABCDEF1234567890')
        ->and(Setting::get('microsoft_clarity_project_id'))->toBe('abcd12345')
        ->and(Setting::get('robots_ai_crawlers_allowed'))->toBeFalse()
        ->and(Setting::get('cookie_consent_enabled'))->toBeTrue()
        ->and(Setting::get('analytics_consent_required'))->toBeTrue();

    // ...and the public site reflects them on the very next request, with no deploy.
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('<meta name="msvalidate.01" content="ABCDEF1234567890">')
        ->and($html)->toContain('Cookie preferences')
        ->and($html)->not->toContain('clarity.ms');

    expect($this->get('/robots.txt')->getContent())->toContain('Disallow: /private-campaign');
});

it('rejects malformed platform IDs and verification tokens', function () {
    Livewire::test(SeoAnalytics::class)
        ->fillForm([
            'google_ads_conversion_id' => 'AW-abc',
            'microsoft_clarity_project_id' => 'no spaces allowed',
            'meta_pixel_id' => 'abc',
            'linkedin_partner_id' => 'abc',
            'bing_webmaster_verification' => 'short',
            'other_verification_meta' => 'just some text',
        ])
        ->call('save')
        ->assertHasFormErrors([
            'google_ads_conversion_id',
            'microsoft_clarity_project_id',
            'meta_pixel_id',
            'linkedin_partner_id',
            'bing_webmaster_verification',
            'other_verification_meta',
        ]);
});

it('hides the whole advanced tab from a permitted admin who is not a super admin', function () {
    $seo = User::factory()->create(['is_admin' => true]);
    $seo->syncRoles([]);
    $seo->givePermissionTo(Permission::findOrCreate('View:SeoAnalytics'));

    Setting::set('custom_css', '.original {}');

    $this->actingAs($seo);

    Livewire::test(SeoAnalytics::class)
        ->assertFormFieldHidden('custom_css')
        ->assertFormFieldHidden('custom_javascript')
        ->fillForm(['seo_meta_description' => 'Set by the SEO role'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('custom_css'))->toBe('.original {}')
        ->and(Setting::get('seo_meta_description'))->toBe('Set by the SEO role');
});
