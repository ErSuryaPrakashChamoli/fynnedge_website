<?php

use App\Enums\PublishStatus;
use App\Models\MarketingSection;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Localization\SiteLocale;
use App\Support\Pages\DebtConsolidationPages;

/**
 * The user asked (2026-11-25) for Debt Consolidation to be preferred across
 * the site: the strip above the header, the desktop nav straight after
 * Loans, the top of the mobile menu, the footer and a homepage feature.
 */
function publishDebtPages(string ...$keys): void
{
    Page::query()
        ->whereIn('slug', array_map(DebtConsolidationPages::slug(...), $keys))
        ->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);
}

function switchDebtConsolidation(array $settings = []): void
{
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true, ...$settings]);
}

it('gives Debt Consolidation first place across the site while it is live', function () {
    switchDebtConsolidation();
    publishDebtPages('hub', 'assessment', 'calculator', 'how_it_works');

    $response = $this->get('/')->assertOk();
    $html = $response->getContent();

    $response->assertSeeInOrder(['data-language-strip', 'data-debt-consolidation-strip', 'data-locale-option="en"', 'data-portal-link', '<header'], false)
        ->assertSeeInOrder(['aria-label="Primary"', 'Loans', 'data-nav-debt-consolidation', 'Credit Score', 'Calculators'], false)
        ->assertSeeInOrder(['id="mobile-nav"', 'Loans', 'data-mobile-debt-consolidation', 'href="'.route('debt-consolidation.assessment').'"', 'Credit Score'], false)
        ->assertSeeInOrder(['<h1', 'data-home-debt-consolidation', 'Get Started with a Quick Enquiry'], false)
        ->assertSee('data-footer-debt-consolidation', false);

    $feature = str($html)->after('data-home-debt-consolidation')->before('</section>')->toString();
    $desktopNav = str($html)->after('aria-label="Primary"')->before('</nav>')->toString();

    expect($feature)
        ->toContain('Paying several EMIs and card bills?')
        ->toContain('href="'.route('debt-consolidation.assessment').'"')
        ->toContain('Start your free assessment')
        ->toContain('href="'.route('debt-consolidation.calculator').'"')
        ->toContain('href="'.route('debt-consolidation.how-it-works').'"')
        // The desktop nav's Eligibility link (same page as the Check Eligibility
        // button) gives its room to Debt Consolidation; the button stays.
        ->and($desktopNav)->not->toMatch('/>\s*Eligibility\s*<\/a>/')
        ->and($html)->toContain('Check Eligibility');
});

it('shows none of it while the service is off or the hub is a draft', function (bool $enabled, bool $hubPublished) {
    if ($enabled) {
        switchDebtConsolidation();
    }

    if ($hubPublished) {
        publishDebtPages('hub', 'assessment');
    }

    $response = $this->get('/')
        ->assertOk()
        ->assertDontSee('data-debt-consolidation-strip', false)
        ->assertDontSee('data-nav-debt-consolidation', false)
        ->assertDontSee('data-mobile-debt-consolidation', false)
        ->assertDontSee('data-footer-debt-consolidation', false)
        ->assertDontSee('data-home-debt-consolidation', false);

    expect(str($response->getContent())->after('aria-label="Primary"')->before('</nav>')->toString())->toMatch('/>\s*Eligibility\s*<\/a>/');
})->with([
    'switched off, hub published' => [false, true],
    'switched on, hub still a draft' => [true, false],
]);

it('points the homepage buttons only at published pages', function () {
    switchDebtConsolidation();
    publishDebtPages('hub');

    $html = $this->get('/')->assertOk()->getContent();
    $feature = str($html)->after('data-home-debt-consolidation')->before('</section>')->toString();
    $mobileItem = str($html)->after('data-mobile-debt-consolidation')->before('Credit Score')->toString();

    expect($feature)
        ->toContain('Explore debt consolidation')
        ->toContain('href="'.route('debt-consolidation.hub').'"')
        ->not->toContain(route('debt-consolidation.assessment'))
        ->not->toContain(route('debt-consolidation.calculator'))
        ->not->toContain(route('debt-consolidation.how-it-works'))
        ->and($mobileItem)->not->toContain('Free assessment');
});

it('takes the homepage wording from the Debt Consolidation marketing section', function () {
    switchDebtConsolidation();
    publishDebtPages('hub', 'assessment');
    MarketingSection::factory()->published()->create([
        'placement' => 'home_debt_consolidation',
        'heading' => 'Juggling five due dates?',
        'subheading' => 'Check whether one loan is cheaper.',
        'description' => 'We count every charge before showing any saving.',
        'cta_label' => 'Check my loans',
        'cta_url' => '/debt-consolidation/calculator',
    ]);

    $feature = str($this->get('/')->getContent())->after('data-home-debt-consolidation')->before('</section>')->toString();

    expect($feature)
        ->toContain('Juggling five due dates?')
        ->toContain('Check whether one loan is cheaper.')
        ->toContain('We count every charge before showing any saving.')
        ->toContain('href="/debt-consolidation/calculator"')
        ->toContain('Check my loans')
        ->not->toContain('Paying several EMIs and card bills?')
        ->not->toContain('Start your free assessment');
});

it('describes only the applicants the service currently accepts', function (array $settings, string $expected, array $absent) {
    switchDebtConsolidation($settings);
    publishDebtPages('hub');

    $feature = str($this->get('/')->getContent())->after('data-home-debt-consolidation')->before('</section>')->toString();

    expect($feature)->toContain($expected);

    foreach ($absent as $line) {
        expect($feature)->not->toContain($line);
    }
})->with([
    'both' => [[], 'For personal and business debt', ['For personal loans and credit cards', 'For business loans and credit lines']],
    'individuals only' => [['business_enabled' => false], 'For personal loans and credit cards', ['For personal and business debt', 'For business loans and credit lines']],
    'businesses only' => [['individual_enabled' => false], 'For business loans and credit lines', ['For personal and business debt', 'For personal loans and credit cards']],
]);

it('shows the strip, menu and homepage feature in Hindi', function () {
    switchDebtConsolidation();
    publishDebtPages('hub', 'assessment');

    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get('/')
        ->assertOk()
        ->assertSee('कई EMI? देखें कि क्या एक लोन सच में कम महंगा पड़ेगा।')
        ->assertSee('मुफ़्त आकलन →')
        ->assertSee('कई EMI और कार्ड बिल चुका रहे हैं?')
        ->assertDontSee('Paying several EMIs and card bills?');
});

it('keeps the original desktop menu spacing while the service is off', function () {
    $offNav = str($this->get('/')->getContent())->after('<nav')->before('aria-label="Primary"')->toString();

    switchDebtConsolidation();
    publishDebtPages('hub');
    $onNav = str($this->get('/')->getContent())->after('<nav')->before('aria-label="Primary"')->toString();

    expect($offNav)->toContain('xl:gap-6')
        ->and($onNav)->not->toContain('xl:gap-6');
});
