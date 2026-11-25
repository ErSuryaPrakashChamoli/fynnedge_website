<?php

use App\Filament\Pages\LeadAnalytics as LeadAnalyticsPage;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Services\LeadAnalytics;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function pageView(string $path, int $count = 1, array $properties = []): void
{
    foreach (range(1, $count) as $ignored) {
        AnalyticsEvent::query()->create(['event_key' => AnalyticsEventKey::PageView, 'properties' => ['path' => $path, ...$properties]]);
    }
}

function lastMonth(): array
{
    return [CarbonImmutable::now()->subDays(29)->startOfDay(), CarbonImmutable::now()->endOfDay()];
}

it('credits leads to their page and shows conversion only where views were recorded', function () {
    $product = LoanProduct::factory()->create(['slug' => 'personal-loan']);
    ContactEnquiry::factory()->forLoanProduct($product)->count(2)->create(['phone_verified_at' => now()]);
    ContactEnquiry::factory()->create(['source_url' => '/contact']);
    pageView('/loans/personal-loan', 40);

    [$from, $to] = lastMonth();
    $pages = collect(LeadAnalytics::report($from, $to, LeadSource::cases())['pages'])->keyBy('page');

    expect($pages['/loans/personal-loan'])->toMatchArray(['views' => 40, 'leads' => 2, 'verified' => 2, 'conversion' => '5%'])
        ->and($pages['/contact'])->toMatchArray(['views' => null, 'leads' => 1, 'conversion' => null]);
});

it('credits an application to the page that linked to /apply', function () {
    config(['app.url' => 'https://fynnedge.test']);
    $session = JourneySession::factory()->create([
        'phone_number' => '9876500000',
        'landing_page' => 'https://fynnedge.test/loans/home-loan/apply?utm_campaign=monsoon',
        'referrer' => 'https://fynnedge.test/loans/home-loan/home-loan-for-women',
        'utm_campaign' => 'monsoon',
    ]);

    [$from, $to] = lastMonth();
    $report = LeadAnalytics::report($from, $to, LeadSource::cases());

    expect(collect($report['pages'])->pluck('page'))->toContain('/loans/home-loan/home-loan-for-women')
        ->and($report['campaigns'][0])->toMatchArray(['campaign' => 'monsoon', 'leads' => 1]);
});

it('says plainly when page views are not tracked instead of inventing rates', function () {
    ContactEnquiry::factory()->create(['source_url' => '/loans/x']);

    [$from, $to] = lastMonth();
    $report = LeadAnalytics::report($from, $to, LeadSource::cases());

    expect($report['tracking_since'])->toBeNull()
        ->and(collect($report['insights'])->pluck('text')->implode(' '))->toContain('not tracked yet')
        ->and(collect($report['kpis'])->firstWhere('label', 'Page views')['value'])->toBe('Not tracked yet');
});

it('keeps OTP verifications out of the lead total and reports them as their own funnel', function () {
    MobileOtpChallenge::factory()->verified()->count(3)->create();
    MobileOtpChallenge::factory()->create();
    ContactEnquiry::factory()->create();

    [$from, $to] = lastMonth();
    $report = LeadAnalytics::report($from, $to, LeadSource::cases());

    expect(collect($report['kpis'])->firstWhere('label', 'Leads')['value'])->toBe('1')
        ->and($report['otp'])->toMatchArray(['requested' => 4, 'verified' => 3, 'rate' => '75%']);
});

it('compares with the previous period of the same length', function () {
    ContactEnquiry::factory()->count(3)->create();
    ContactEnquiry::factory()->count(2)->create(['created_at' => now()->subDays(40)]);

    [$from, $to] = lastMonth();
    $leads = collect(LeadAnalytics::report($from, $to, LeadSource::cases())['kpis'])->firstWhere('label', 'Leads');

    expect($leads)->toMatchArray(['value' => '3', 'previous' => '2', 'change' => 50.0]);
});

it('renders the analytics page and its filters for lead staff', function () {
    ContactEnquiry::factory()->create(['source_url' => '/quick-enquiry']);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(LeadAnalyticsPage::getUrl(['range' => '7', 'granularity' => 'week']))
        ->assertOk()
        ->assertSeeText('Page performance')
        ->assertSeeText('/quick-enquiry');
});

it('counts real public page views without cookies or personal data', function () {
    $product = LoanProduct::factory()->published()->create();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130')
        ->get('/loans/'.$product->slug.'?utm_source=google&utm_campaign=diwali')
        ->assertOk();

    $view = AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::PageView)->sole();

    expect($view->properties)->toBe(['path' => '/loans/'.$product->slug, 'utm_source' => 'google', 'utm_campaign' => 'diwali'])
        ->and($view->loan_product_id)->toBe($product->id)
        ->and(DB::table('analytics_events')->where('id', $view->id)->value('journey_session_id'))->toBeNull();
});

it('does not count bots, prefetches, admin screens or the customer portal', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/')->assertOk();
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/130', 'Sec-Purpose' => 'prefetch'])->get('/')->assertOk();
    $this->withHeader('User-Agent', 'Mozilla/5.0 Chrome/130')->get('/admin')->assertOk();

    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::PageView)->count())->toBe(0);
});

it('prunes old page views but never funnel milestones', function () {
    AnalyticsEvent::query()->forceCreate(['event_key' => AnalyticsEventKey::PageView, 'properties' => ['path' => '/'], 'created_at' => now()->subDays(500)]);
    AnalyticsEvent::query()->forceCreate(['event_key' => AnalyticsEventKey::JourneyStarted, 'created_at' => now()->subDays(500)]);

    $this->artisan('model:prune', ['--model' => [AnalyticsEvent::class]])->assertSuccessful();

    expect(AnalyticsEvent::query()->pluck('event_key')->all())->toBe([AnalyticsEventKey::JourneyStarted]);
});
