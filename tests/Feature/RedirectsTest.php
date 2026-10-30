<?php

use App\Console\Commands\SeoAuditCommand;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Models\Redirect;
use App\Models\User;
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
