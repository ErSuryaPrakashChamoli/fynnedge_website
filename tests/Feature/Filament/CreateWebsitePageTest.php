<?php

use App\Enums\PublishStatus;
use App\Filament\Pages\CreateWebsitePage;
use App\Filament\Resources\LoanLandingPages\Pages\CreateLoanLandingPage;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use App\Support\Seo\Sitemap;
use App\Support\UrlPath;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('explains each page type and blocks a landing page until a loan product exists', function () {
    Livewire::test(CreateWebsitePage::class)
        ->assertSeeText('General website page')
        ->assertSeeText('Loan landing page')
        ->assertSeeText('Campaign landing page')
        ->assertSeeText('Blog article')
        ->call('choose', 'landing')
        ->assertSeeText('fynnedge.com/loans/{product}/{address}')
        ->assertSeeText('Complete the highlighted step first')
        ->assertDontSeeText('Create loan landing page');

    LoanProduct::factory()->create();

    Livewire::test(CreateWebsitePage::class, ['type' => 'landing'])
        ->assertSeeText('Ready — open the editor')
        ->assertSeeText('Create loan landing page');
});

it('opens a campaign landing page with noindex pre-selected', function () {
    Livewire::withQueryParams(['purpose' => 'campaign'])
        ->test(CreateLoanLandingPage::class)
        ->assertSchemaStateSet(['seoMeta.robots' => 'noindex, follow']);

    Livewire::withQueryParams([])
        ->test(CreateLoanLandingPage::class)
        ->assertSchemaStateSet(['seoMeta.robots' => 'index, follow']);
});

it('publishes a new general page at its own address, with preview and sitemap entry', function () {
    $page = Page::factory()->create(['slug' => 'home-loan-guide', 'title' => 'Home loan guide']);

    $this->get('/home-loan-guide')->assertNotFound();

    $page->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    $this->get('/home-loan-guide')->assertOk()->assertSeeText('Home loan guide');
    $this->get('/sitemap.xml')->assertOk()->assertSee(url('/home-loan-guide'), false);

    expect(Sitemap::pageUrl('home-loan-guide', absolute: false))->toBe('/home-loan-guide');

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertActionVisible('preview');
});

it('refuses an address another part of the site already answers or a redirect takes', function (string $slug, string $message) {
    if ($slug === 'old-offer') {
        Redirect::query()->create(['source_path' => '/old-offer', 'destination' => '/loans', 'status_code' => 301, 'is_active' => true]);
    }

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Anything', 'slug' => $slug, 'status' => PublishStatus::Draft])
        ->call('create')
        ->assertHasFormErrors(['slug' => fn (array $failedRules, array $messages): bool => collect($messages)->contains(fn (string $text): bool => str_contains($text, $message))]);
})->with([
    'an existing route' => ['contact', 'already used by another part of the website'],
    'the health check' => ['up', 'already used by another part of the website'],
    'a redirect' => ['old-offer', 'An active redirect sends'],
    'bad characters' => ['Home_Loan', 'lowercase letters'],
]);

it('still serves the fixed pages from their own routes', function () {
    Page::factory()->published()->create(['slug' => 'terms']);

    $this->get('/terms')->assertOk();

    expect(Sitemap::pageRoute('terms'))->toBe(['terms', []])
        ->and(Sitemap::pageRoute('about'))->toBe(['about', []]);
});

it('marks fixed and general pages in the list and offers publish and unpublish', function () {
    $general = Page::factory()->create(['slug' => 'partnerships']);

    Livewire::test(ListPages::class)
        ->assertTableColumnStateSet('page_type', 'General page', $general)
        ->assertTableColumnStateSet('seo_status', 'Missing', $general)
        ->callTableAction('publish', $general);

    expect($general->refresh()->status)->toBe(PublishStatus::Published);
});

it('redirects a slash variant to a published general page in one hop, and 404s for anything else', function () {
    Page::factory()->published()->create(['slug' => 'home-loan-guide']);

    // The test client trims trailing slashes, so hand the raw request to the kernel (see tests.md).
    $exact = function (string $path): TestResponse {
        $request = Request::create(config('app.url').$path);
        $response = app(Kernel::class)->handle($request);

        return TestResponse::fromBaseResponse($response, $request);
    };

    $exact('/home-loan-guide/')->assertStatus(301)->assertRedirect(url('/home-loan-guide'));
    $exact('/no-such-page/')->assertNotFound();

    expect(UrlPath::isRoutable('/home-loan-guide'))->toBeTrue()
        ->and(UrlPath::isRoutable('/no-such-page'))->toBeFalse();
});
