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
 * the site: the strip above the header, the top of the mobile menu, the
 * footer and a homepage feature. Since 2026-11-26 it stays out of the desktop
 * nav, and the strip is a highlighted Debt Consolidation band instead.
 */
function desktopNav(string $html): string
{
    preg_match('/<nav[^>]*aria-label="Primary">.*?<\/nav>/s', $html, $matches);

    return $matches[0];
}
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
        ->assertSeeInOrder(['id="mobile-nav"', 'Loans', 'data-mobile-debt-consolidation', 'href="'.route('debt-consolidation.assessment').'"', 'Credit Score'], false)
        ->assertSeeInOrder(['<h1', 'data-home-debt-consolidation', 'Get Started with a Quick Enquiry'], false)
        ->assertSee('data-footer-debt-consolidation', false);

    $feature = str($html)->after('data-home-debt-consolidation')->before('</section>')->toString();
    $strip = str($html)->after('data-debt-consolidation-strip')->before('data-locale-option')->toString();

    expect($feature)
        ->toContain('Paying several EMIs and card bills?')
        ->toContain('href="'.route('debt-consolidation.assessment').'"')
        ->toContain('Start your free assessment')
        ->toContain('href="'.route('debt-consolidation.calculator').'"')
        ->toContain('Analyse my loans')
        ->toContain('href="'.route('debt-consolidation.how-it-works').'"')
        // The whole strip turns into the highlighted Debt Consolidation band,
        // with the hub link and a Free assessment button.
        ->and($html)->toMatch('/<div class="border-b dc-strip" data-language-strip>/')
        ->and($strip)
        ->toContain('href="'.route('debt-consolidation.hub').'"')
        ->toMatch('/href="'.preg_quote(route('debt-consolidation.assessment'), '/').'"[^>]*data-debt-consolidation-strip-cta>\s*Free assessment/');
});

it('leaves the desktop menu as it is without the service while the service is live', function () {
    $offNav = desktopNav($this->get('/')->getContent());

    switchDebtConsolidation();
    publishDebtPages('hub', 'assessment');
    $onNav = desktopNav($this->get('/')->getContent());

    // The only difference: the Calculators menu lists the Savings Calculator,
    // which is public only while the service is live (CalculatorCatalog).
    $savingsCalculator = route('calculators.debt-consolidation');
    $savingsCalculatorLink = '/\s*<a\s+href="'.preg_quote($savingsCalculator, '/').'"[^>]*>\s*Debt Consolidation Savings Calculator\s*<\/a>/';

    expect($offNav)->not->toContain($savingsCalculator)
        ->and($onNav)->toContain($savingsCalculator)
        ->and(preg_replace($savingsCalculatorLink, '', $onNav))->toBe($offNav)
        ->and($onNav)->toContain('xl:gap-6')
        ->not->toContain(route('debt-consolidation.hub'));
});

it('keeps the Debt Consolidation strip in view by sticking it to the top together with the header', function () {
    switchDebtConsolidation();
    publishDebtPages('hub');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toMatch('/<div\s+class="sticky top-0 z-40"\s+x-data\s+x-init="new ResizeObserver\(.*setProperty\(\'--site-header-height\'.*"\s+data-site-top\s*>\s*<div class="border-b dc-strip" data-language-strip>/')
        ->toMatch('/<\/header>\s*<\/div>\s*<main/')
        // Only the wrapper sticks; a sticky <header> inside it would detach from the strip.
        ->toContain('<header class="relative border-b');
});

it('pops the strip\'s Free assessment chip to draw the eye, except on hover, focus or with reduced motion', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toMatch('/\.dc-strip-cta \{[^}]*animation: dc-strip-cta-pop 3\.2s[^}]*\}/')
        ->toMatch('/\.dc-strip-cta::before \{[^}]*animation: dc-strip-cta-fill[^}]*\}/')
        ->toMatch('/\.dc-strip-cta::after \{[^}]*animation: dc-strip-cta-halo[^}]*\}/')
        ->toMatch('/\.dc-strip-cta:hover,\s*\.dc-strip-cta:focus-visible,[^{]*\{\s*animation: none;\s*\}/')
        ->toMatch('/@media \(prefers-reduced-motion: reduce\) \{\s*\.dc-strip-icon,\s*\.dc-strip-cta,\s*\.dc-strip-cta::before,\s*\.dc-strip-cta::after \{\s*animation: none;/');
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
        ->assertSee('<div class="border-b border-line bg-surface" data-language-strip>', false)
        ->assertDontSee('data-mobile-debt-consolidation', false)
        ->assertDontSee('data-footer-debt-consolidation', false)
        ->assertDontSee('data-home-debt-consolidation', false);
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
    $strip = str($html)->after('data-debt-consolidation-strip')->before('data-locale-option')->toString();

    expect($feature)
        ->toContain('Explore debt consolidation')
        ->toContain('href="'.route('debt-consolidation.hub').'"')
        ->not->toContain(route('debt-consolidation.assessment'))
        ->not->toContain(route('debt-consolidation.calculator'))
        ->not->toContain(route('debt-consolidation.how-it-works'))
        ->and($mobileItem)->not->toContain('Free assessment')
        ->and($strip)->toContain('href="'.route('debt-consolidation.hub').'"')
        ->not->toContain('data-debt-consolidation-strip-cta');
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
