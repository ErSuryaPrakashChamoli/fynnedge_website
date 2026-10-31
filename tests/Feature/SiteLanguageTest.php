<?php

use App\Modules\Customers\Models\Customer;
use App\Support\Localization\SiteLocale;
use Illuminate\Http\Request;

it('renders the language switch in the top strip with English selected by default', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSee('<html lang="en"', false);
    $response->assertSee('<meta property="og:locale" content="en_IN">', false);
    $response->assertSeeInOrder(['data-language-strip', 'data-locale-option="en"', 'data-locale-option="hi"', '<header'], false);

    expect(preg_match('/<button[^>]*data-locale-option="en"[^>]*aria-current="true"/', $response->getContent()))->toBe(1)
        ->and(preg_match('/<button[^>]*data-locale-option="hi"[^>]*aria-current="true"/', $response->getContent()))->toBe(0);
});

it('puts the Sign in pill in the top-right corner, after the language switch', function () {
    $response = $this->get('/')->assertOk();

    $response->assertSeeInOrder(['data-language-strip', 'data-locale-option="hi"', 'data-portal-link', 'Sign in', '<header'], false);
    $response->assertDontSee('Existing Customer?');
});

it('shows a signed-in customer My Profile in place of Sign in', function () {
    $this->actingAs(Customer::factory()->portalAccount()->create(), 'customer')
        ->get('/')
        ->assertOk()
        ->assertSeeInOrder(['data-locale-option="hi"', route('portal.dashboard'), 'My Profile', '<header'], false);
});

it('posts each language back to the page the visitor is on', function () {
    $this->get('/?utm_source=x')
        ->assertSeeInOrder([
            '<form method="POST" action="'.route('locale.switch', ['locale' => 'hi']).'"',
            '<input type="hidden" name="return" value="/?utm_source=x">',
            'data-locale-option="hi"',
        ], false);
});

it('never renders the switch as a link, so crawlers find no /language/ URL to crawl', function () {
    $content = $this->get('/loans?type=m')->getContent();

    expect($content)->toContain('data-locale-option="hi"')
        ->not->toMatch('#<a\b[^>]*href="[^"]*/language/#');
});

it('switches language from the posted form and returns to the same page', function () {
    $this->post('/language/hi', ['return' => '/about?utm_source=x'])
        ->assertRedirect('/about?utm_source=x')
        ->assertPlainCookie(SiteLocale::COOKIE, 'hi');
});

it('sends an off-site posted return path to the home page instead', function () {
    $this->post('/language/hi', ['return' => '//evil.example/phish'])->assertRedirect('/');
});

it('remembers the chosen language in a plain cookie and returns to the same page', function () {
    $this->get('/language/hi?return=/about')
        ->assertRedirect('/about')
        ->assertPlainCookie(SiteLocale::COOKIE, 'hi');
});

it('renders public pages in Hindi once Hindi is chosen', function () {
    $response = $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')->get('/')->assertOk();

    $response->assertSee('<html lang="hi"', false);
    $response->assertSee('<meta property="og:locale" content="hi_IN">', false);
    expect(preg_match('/<button[^>]*data-locale-option="hi"[^>]*aria-current="true"/', $response->getContent()))->toBe(1);
});

it('falls back to English for an unknown language in the cookie', function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'fr')
        ->get('/')
        ->assertOk()
        ->assertSee('<html lang="en"', false);
});

it('returns 404 for a language the site is not written in', function () {
    $this->get('/language/fr?return=/about')->assertNotFound();
});

it('sends an off-site return path to the home page instead', function (string $returnPath) {
    $this->get('/language/hi?return='.urlencode($returnPath))->assertRedirect('/');
})->with([
    'protocol-relative' => '//evil.example/phish',
    'absolute URL' => 'https://evil.example/phish',
    'backslash trick' => '/\\evil.example',
    'relative path' => 'about',
]);

it('keeps the admin panel in English for a visitor who chose Hindi', function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get('/admin/login')
        ->assertOk()
        ->assertSee('<html', false)
        ->assertSee('lang="en"', false);
});

it('ignores the language choice for Livewire updates sent from an admin page', function (string $originalPath, bool $applies) {
    $request = Request::create('/livewire/update', 'POST', [
        'components' => [['snapshot' => json_encode(['memo' => ['path' => $originalPath]])]],
    ]);
    $request->headers->set('X-Livewire', '1');
    app()->instance('request', $request);

    expect(SiteLocale::appliesTo($request))->toBe($applies);
})->with([
    'admin page' => ['admin/pages/1/edit', false],
    'public calculator' => ['calculators/emi-calculator', true],
]);

it('keeps Googlebot off the language switch in the robots.txt group it obeys', function () {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect(robotsRulesFor('Googlebot', $robots))->toContain('Disallow: /language/');
});

it('shows the not-found page in Hindi for a URL that matches no route', function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get('/no-such-page-anywhere')
        ->assertNotFound()
        ->assertSee('<html lang="hi"', false)
        ->assertSee(__('Not Found', locale: 'hi'))
        ->assertDontSee('Not Found');
});
