<?php

use App\Models\ContactEnquiry;
use App\Support\Localization\ScriptTranslations;
use App\Support\Localization\SiteLocale;

it('answers a failed public form in the visitor\'s language', function (?string $locale, array $expectedErrors) {
    if ($locale) {
        $this->withUnencryptedCookie(SiteLocale::COOKIE, $locale);
    }

    $this->from('/contact')
        ->post('/contact', ['name' => '', 'email' => 'jordan@example.com', 'message' => ''])
        ->assertRedirect('/contact')
        ->assertSessionHasErrors($expectedErrors);

    expect(ContactEnquiry::query()->count())->toBe(0);
})->with([
    'English' => [null, [
        'name' => 'The name field is required.',
        'message' => 'The message field is required.',
    ]],
    'Hindi' => ['hi', [
        'name' => 'नाम भरना ज़रूरी है।',
        'message' => 'संदेश भरना ज़रूरी है।',
    ]],
]);

it('renders a public page\'s error page in Hindi', function () {
    // No About page exists, so the controller's firstOrFail() answers 404.
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')
        ->get('/about')
        ->assertNotFound()
        ->assertSee('पेज नहीं मिला')
        ->assertDontSee('Not Found');
});

it('hands the page script its interface text in Hindi while Hindi is chosen', function () {
    $content = $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi')->get('/')->assertOk()->getContent();

    preg_match('#<script type="application/json" id="site-translations">(.*?)</script>#s', $content, $match);

    expect(json_decode($match[1] ?? 'null', true))
        ->toBeArray()
        ->toHaveKey('Something went wrong. Please try again.', 'कुछ गड़बड़ हो गई। कृपया फिर से कोशिश करें।')
        ->toHaveKey('Please enter the :length-digit code we sent you.', 'कृपया हमारा भेजा हुआ :length अंकों का कोड डालें।');
});

it('gives English pages no script translations', function () {
    $this->get('/')->assertOk()->assertDontSee('site-translations', false);
});

it('has Hindi for every string the page script translates', function () {
    app()->setLocale('hi');

    expect(array_keys(ScriptTranslations::forCurrentLocale()))->toBe(ScriptTranslations::KEYS);
});

it('lists every string app.js passes to t()', function () {
    preg_match_all("/\\bt\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents(resource_path('js/app.js')), $matches);

    $scriptKeys = array_unique(array_map(fn (string $key): string => stripcslashes($key), $matches[1]));

    expect($scriptKeys)->not->toBeEmpty()
        ->and(array_diff($scriptKeys, ScriptTranslations::KEYS))->toBe([]);
});
