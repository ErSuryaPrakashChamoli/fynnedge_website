<?php

use App\Console\Commands\SeoAuditCommand;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Models\AuditLog;
use App\Models\Redirect;
use App\Models\User;
use App\Support\UrlPath;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * GETs a path exactly as written. The test client sends every URL through
 * url(), which drops a trailing slash and collapses a leading "//" — the very
 * variants under test — so this hands the request to the HTTP kernel itself.
 */
function getExactPath(string $path): TestResponse
{
    $kernel = app(Kernel::class);
    $request = Request::create(config('app.url').$path);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return TestResponse::fromBaseResponse($response, $request);
}

it('sends a 301 to the destination for an active redirect', function () {
    Redirect::factory()->create(['source_path' => '/old-loan-page', 'destination' => '/loans']);

    $this->get('/old-loan-page')->assertRedirect(url('/loans'))->assertStatus(301);
});

it('honours a 302 for a temporary redirect', function () {
    Redirect::factory()->temporary()->create(['source_path' => '/campaign', 'destination' => '/loans']);

    $this->get('/campaign')->assertStatus(302);
});

it('ignores an inactive redirect', function () {
    Redirect::factory()->inactive()->create(['source_path' => '/dormant', 'destination' => '/loans']);

    $this->get('/dormant')->assertNotFound();
});

it('matches regardless of trailing slash, casing or query string, and carries the query across', function () {
    Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);

    $this->get('/Old-Page?utm_source=email')->assertRedirect(url('/loans').'?utm_source=email');
});

it('redirects to an external URL untouched', function () {
    Redirect::factory()->create(['source_path' => '/partner', 'destination' => 'https://partner.example/apply']);

    $this->get('/partner')->assertRedirect('https://partner.example/apply');
});

it('takes precedence over a live route so a renamed page can keep its old URL', function () {
    Redirect::factory()->create(['source_path' => '/loans', 'destination' => '/']);

    $this->get('/loans')->assertRedirect(url('/'));
});

it('leaves non-GET requests alone, so a redirect cannot silently drop a form submission', function () {
    Redirect::factory()->create(['source_path' => '/old-form', 'destination' => '/loans']);

    // A POST to the same path must fall through to normal routing (no route here,
    // so 404/405) rather than being turned into a redirect that discards the body.
    expect($this->post('/old-form', [])->status())->not->toBeIn([301, 302]);
});

it('serves the page instead of looping when a row points at its own source', function () {
    // Bypasses the form's validation on purpose: this is the runtime guard for a
    // row that became self-referential some other way.
    Redirect::query()->create(['source_path' => '/loans', 'destination' => '/loans', 'status_code' => 301, 'is_active' => true]);

    $this->get('/loans')->assertOk();
});

it('counts each use so an admin can see which old URLs still get traffic', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);

    $this->get('/old-page');
    $this->get('/old-page');

    $redirect->refresh();

    expect($redirect->hits)->toBe(2)
        ->and($redirect->last_used_at)->not->toBeNull();
});

it('reads the redirect set from cache rather than querying it on every request', function () {
    Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);

    // Warm the cache, then watch for any further reads of the redirects table.
    Redirect::activeMap();

    $redirectQueries = 0;
    DB::listen(function ($query) use (&$redirectQueries) {
        if (str_contains($query->sql, 'redirects') && str_starts_with(strtolower($query->sql), 'select')) {
            $redirectQueries++;
        }
    });

    $this->get('/')->assertOk();

    expect($redirectQueries)->toBe(0);
});

it('drops the cached map as soon as a redirect changes', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);
    $this->get('/old-page')->assertRedirect(url('/loans'));

    $redirect->update(['destination' => '/about']);

    $this->get('/old-page')->assertRedirect(url('/about'));

    $redirect->delete();

    $this->get('/old-page')->assertNotFound();
});

it('rejects a redirect that points at its own source', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateRedirect::class)
        ->fillForm(['source_path' => '/loop', 'destination' => '/loop', 'status_code' => 301, 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['destination']);
});

it('rejects a redirect that would complete a loop through another row', function () {
    Redirect::factory()->create(['source_path' => '/b', 'destination' => '/a']);

    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateRedirect::class)
        ->fillForm(['source_path' => '/a', 'destination' => '/b', 'status_code' => 301, 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['destination']);
});

it('rejects a second redirect for the same path, however it is typed', function () {
    Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);

    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateRedirect::class)
        ->fillForm(['source_path' => 'Old-Page/', 'destination' => '/about', 'status_code' => 301, 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['source_path']);
});

it('rejects a destination that is neither a path nor a URL', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateRedirect::class)
        ->fillForm(['source_path' => '/old', 'destination' => 'loans', 'status_code' => 301, 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['destination']);
});

it('normalises the stored path so matching never depends on how it was typed', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateRedirect::class)
        ->fillForm(['source_path' => 'Old-Page/', 'destination' => '/loans', 'status_code' => 301, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    // Not "the first row": the legacy-URL migration ships rows into every database.
    $this->assertDatabaseHas('redirects', ['source_path' => '/old-page', 'destination' => '/loans']);
});

it('sends a chain of rows straight to the last destination in one hop', function () {
    Redirect::factory()->create(['source_path' => '/first-name', 'destination' => '/second-name']);
    Redirect::factory()->create(['source_path' => '/second-name', 'destination' => '/loans']);

    $this->get('/first-name')->assertStatus(301)->assertRedirect(url('/loans'));
});

it('makes a chained redirect temporary when any hop in it is temporary', function () {
    Redirect::factory()->create(['source_path' => '/first-name', 'destination' => '/second-name']);
    Redirect::factory()->temporary()->create(['source_path' => '/second-name', 'destination' => '/loans']);

    $this->get('/first-name')->assertStatus(302)->assertRedirect(url('/loans'));
});

it('serves the page instead of looping when two rows point at each other', function () {
    // Bypasses the form, which rejects this pair, as the runtime guard for rows that got there some other way.
    Redirect::query()->create(['source_path' => '/loans', 'destination' => '/old-loans', 'status_code' => 301, 'is_active' => true]);
    Redirect::query()->create(['source_path' => '/old-loans', 'destination' => '/loans', 'status_code' => 301, 'is_active' => true]);

    $this->get('/loans')->assertOk();
});

it('reaches a row from its trailing-slash URL in one hop, not via the slashless URL', function () {
    Redirect::factory()->create(['source_path' => '/business-loan-3', 'destination' => '/loans/business-loan']);

    getExactPath('/business-loan-3/')->assertStatus(301)->assertRedirect(url('/loans/business-loan'));
});

it('drops the trailing slash from a live page with one 301 and keeps the query string', function () {
    getExactPath('/loans/?utm_source=email')->assertStatus(301)->assertRedirect(url('/loans').'?utm_source=email');
});

it('collapses doubled slashes into the live page they name', function () {
    getExactPath('//loans')->assertStatus(301)->assertRedirect(url('/loans'));
});

it('answers a slash variant of an unknown URL with its 404 rather than a redirect to one', function (string $path) {
    getExactPath($path)->assertNotFound();
})->with([
    'deleted WordPress page' => '/loan-central/',
    'WordPress REST API' => '/wp-json/elementskit/v1/',
]);

it('never turns a doubled leading slash into an off-site redirect', function () {
    getExactPath('//evil.example/')->assertNotFound();
});

it('does not count the seo:audit command\'s own requests as traffic', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);

    $this->withHeader('User-Agent', SeoAuditCommand::USER_AGENT)->get('/old-page')->assertRedirect(url('/loans'));

    expect($redirect->refresh()->hits)->toBe(0);
});

it('redirects the legacy WordPress URLs that have a clear equivalent page in one hop', function (string $legacyUrl, string $destination) {
    getExactPath($legacyUrl)->assertStatus(301)->assertRedirect(url($destination));
})->with([
    'credit card page' => ['/credit-card/', '/loans/credit-card'],
    'fixed vs floating article' => ['/fixed-versus-floating-interest-rates-on-personal-loans-which-one-is-superior/', '/resources/fixed-vs-floating-interest-rates'],
]);

it('leaves an admin\'s own row for a legacy URL alone when the legacy-URL migration runs', function () {
    Redirect::query()->where('source_path', '/credit-card')->sole()->update(['destination' => '/loans', 'is_active' => false]);

    (require database_path('migrations/2026_10_30_072910_add_legacy_wordpress_redirects.php'))->up();

    expect(Redirect::query()->where('source_path', '/credit-card')->sole())
        ->destination->toBe('/loans')
        ->is_active->toBeFalse();
});

it('answers 410 for a Gone row from its trailing-slash URL and counts the hit', function () {
    $gone = Redirect::factory()->gone()->create(['source_path' => '/retired-article']);

    getExactPath('/retired-article/')->assertGone()->assertSee('This page has been removed');

    expect($gone->refresh()->hits)->toBe(1);
});

it('answers 410 straight away when a redirect chain ends at a Gone row', function () {
    Redirect::factory()->create(['source_path' => '/old-name', 'destination' => '/retired-article']);
    Redirect::factory()->gone()->create(['source_path' => '/retired-article']);

    $this->get('/old-name')->assertGone();
});

it('saves a Gone rule with no destination and keeps its note', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateRedirect::class)
        ->fillForm(['source_path' => '/retired-article/', 'status_code' => Redirect::GONE, 'is_active' => true, 'notes' => 'Old WordPress post'])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('redirects', ['source_path' => '/retired-article', 'destination' => null, 'status_code' => 410, 'notes' => 'Old WordPress post']);
});

it('drops the destination when a redirect is turned into a Gone rule', function () {
    $redirect = Redirect::factory()->create(['source_path' => '/retired-article', 'destination' => '/loans']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(EditRedirect::class, ['record' => $redirect->getRouteKey()])
        ->fillForm(['status_code' => Redirect::GONE])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($redirect->refresh())
        ->status_code->toBe(410)
        ->destination->toBeNull();
});

it('rejects a destination that cannot take a visitor to a live page in one hop', function (?array $existingRow, string $destination, string $message) {
    if ($existingRow) {
        Redirect::factory()->create($existingRow);
    }
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateRedirect::class)
        ->fillForm(['source_path' => '/old-page', 'destination' => $destination, 'status_code' => Redirect::PERMANENT, 'is_active' => true])
        ->call('create')
        // A closure, not [$message]: Livewire reads "a:b" in an expected message as a rule with parameters.
        ->assertHasFormErrors(['destination' => fn (array $failedRules, array $messages): bool => in_array($message, $messages, true)]);

    $this->assertDatabaseMissing('redirects', ['source_path' => '/old-page']);
})->with([
    'another old URL, which would make a chain' => [
        ['source_path' => '/second-name', 'destination' => '/loans'],
        '/second-name',
        'That path already redirects to /loans. Enter that destination instead, so visitors take one hop.',
    ],
    'a path marked Gone' => [
        ['source_path' => '/retired-article', 'destination' => null, 'status_code' => Redirect::GONE],
        '/retired-article',
        'That path is itself marked Gone (410). Choose a page that exists, or make this row Gone too.',
    ],
    'a path no route answers' => [null, '/loan/personal-loan', 'No page on this site answers that path. Check it for typos.'],
    'a URL that is not http(s)' => [null, 'javascript://example.com/%0Aalert(1)', 'Enter a path starting with / or a complete URL including https://.'],
]);

it('records who changed a redirect and what changed, without the hit counter', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $redirect = Redirect::factory()->create(['source_path' => '/old-page', 'destination' => '/loans']);
    $this->actingAs($admin);

    Livewire::test(EditRedirect::class, ['record' => $redirect->getRouteKey()])
        ->fillForm(['destination' => '/about'])
        ->call('save')
        ->assertHasNoFormErrors();

    $entry = AuditLog::query()->where('auditable_type', $redirect->getMorphClass())->where('auditable_id', $redirect->id)->where('action', 'updated')->sole();
    expect($entry)
        ->user_id->toBe($admin->id)
        ->changes->toBe(['destination' => ['old' => '/loans', 'new' => '/about']]);
});

it('prefills the old path from the Missing pages list', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(CreateRedirect::getUrl(['source_path' => '/old-page']))->assertOk();
    Livewire::withQueryParams(['source_path' => '/old-page'])
        ->test(CreateRedirect::class)
        ->assertSchemaStateSet(['source_path' => '/old-page']);
});

it('sends each old WordPress URL from the October 2026 404 report to its equivalent page in one 301', function (string $legacyUrl, string $destination) {
    $response = getExactPath($legacyUrl);

    $response->assertMovedPermanently()->assertRedirect(url($destination));
    expect(UrlPath::isRoutable($destination))->toBeTrue();
})->with([
    'personal loan page' => ['/personal-loan/', '/loans/personal-loans'],
    'personal loan page without a slash' => ['/personal-loan', '/loans/personal-loans'],
    'home loan page' => ['/home-loan/', '/loans/home-loan'],
    'home loan page without a slash' => ['/home-loan', '/loans/home-loan'],
    'business loan page' => ['/business-loan/', '/loans/business-loan'],
    'overdraft page' => ['/overdraft-facilities/', '/loans/flexi-hybrid-term-loan'],
    'contact page' => ['/contact-us/', '/contact'],
    'careers page' => ['/career/', '/careers'],
    'team page' => ['/team/', '/about'],
    'photo gallery' => ['/gallery/', '/about'],
    'personal loan calculator' => ['/personal-loan-calculator/', '/calculators/emi/personal-loan'],
    'duplicate personal loan calculator' => ['/personal-loan-calculator-2/', '/calculators/emi/personal-loan'],
    'duplicate car loan calculator' => ['/car-loan-calculator-2/', '/calculators/emi/car-loan'],
    'CIBIL score page' => ['/cibil-score/', '/credit-score/cibil'],
    'holiday article' => ['/tips-for-using-a-personal-loan-for-your-dream-holiday/', '/loans/personal-loans/personal-loan-for-travel'],
    'combining loans article' => ['/how-to-combine-multiple-loans-and-reduce-your-emi-burden-in-india-in-2026/', '/loans/personal-loans/personal-loan-for-debt-consolidation'],
    'self-employed article' => ['/are-self-employed-people-eligible-for-a-personal-loan-without-an-itr/', '/loans/personal-loans/personal-loan-for-self-employed'],
    'best personal loan article' => ['/best-personal-loan-in-india-2026-a-definitive-ranking-of-top-banks-and-nbfcs/', '/loans/personal-loans'],
    'startup business loan article' => ['/unsecured-business-loans-for-startups-a-2026-guide-to-capital-without-collateral/', '/loans/business-loan'],
]);

it('answers 410 for each retired page from the October 2026 404 report', function (string $legacyUrl) {
    getExactPath($legacyUrl)->assertGone();
})->with([
    '/the-home-loan-advantage/',
    '/taking-out-a-personal-loan-on-probation-is-it-even-an-option/',
    '/the-personal-loan-master-guide/',
    '/the-msme-growth-manual/',
    '/the-credit-score-recovery-roadmap/',
    '/how-loan-prepayment-can-save-you-lakhs-in-interest-in-india-in-2026/',
    '/how-to-manage-your-emis-smartly-in-2026-and-stay-financially-stress-free/',
    '/comments/feed/',
    '/2026/03/03/',
]);

it('keeps answering 404 for the October 2026 report URLs that have no equivalent', function (string $legacyUrl) {
    getExactPath($legacyUrl)->assertNotFound();
})->with(['/mutual-fund/', '/debit-card/', '/review/', '/lander']);

it('leaves an admin\'s own row alone when the October 2026 migration runs, and when it is rolled back', function () {
    $migration = require database_path('migrations/2026_10_31_084215_add_october_2026_not_found_legacy_urls.php');
    Redirect::query()->where('source_path', '/team')->sole()->update(['destination' => '/careers']);

    $migration->up();
    $migration->down();

    expect(Redirect::query()->where('source_path', '/team')->sole())->destination->toBe('/careers')
        ->and(Redirect::query()->where('source_path', '/career')->exists())->toBeFalse()
        ->and(Redirect::query()->where('source_path', '/comments/feed')->exists())->toBeFalse();
});
