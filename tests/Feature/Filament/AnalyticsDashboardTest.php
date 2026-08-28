<?php

use App\Filament\Pages\AnalyticsDashboard;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Journey\Models\JourneySession;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('renders the analytics dashboard page', function () {
    $this->get('/admin/analytics-dashboard')->assertOk();
});

it('computes funnel counts and conversion rates from real events', function () {
    $product = LoanProduct::factory()->published()->create();
    $started = JourneySession::factory()->count(4)->create(['loan_product_id' => $product->id]);
    $completed = $started->take(2);

    foreach ($started as $session) {
        AnalyticsEvent::factory()->create([
            'event_key' => AnalyticsEventKey::JourneyStarted,
            'journey_session_id' => $session->id,
            'loan_product_id' => $product->id,
        ]);
    }
    foreach ($completed as $session) {
        AnalyticsEvent::factory()->create([
            'event_key' => AnalyticsEventKey::JourneyCompleted,
            'journey_session_id' => $session->id,
            'loan_product_id' => $product->id,
        ]);
    }

    $component = Livewire::test(AnalyticsDashboard::class)
        ->set('loanProductId', $product->id)
        ->set('range', 'all');

    $funnel = collect($component->instance()->funnel())->keyBy('label');

    expect($funnel['Journeys started']['count'])->toBe(4);
    expect($funnel['Journeys completed']['count'])->toBe(2);
    expect($funnel['Journeys completed']['conversion'])->toBe(50.0);
});

it('excludes events outside the selected date range', function () {
    $product = LoanProduct::factory()->published()->create();
    $session = JourneySession::factory()->create(['loan_product_id' => $product->id]);

    $oldEvent = AnalyticsEvent::factory()->create([
        'event_key' => AnalyticsEventKey::JourneyStarted,
        'journey_session_id' => $session->id,
        'loan_product_id' => $product->id,
    ]);
    $oldEvent->forceFill(['created_at' => now()->subDays(60)])->save();

    $component = Livewire::test(AnalyticsDashboard::class)
        ->set('loanProductId', $product->id)
        ->set('range', '30');

    $funnel = collect($component->instance()->funnel())->keyBy('label');

    expect($funnel['Journeys started']['count'])->toBe(0);
});
