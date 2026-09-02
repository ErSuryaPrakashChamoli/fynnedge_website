<?php

use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Applications\Actions\SetApplicationAssistancePreference;
use App\Modules\Applications\Enums\AssistancePreference;
use App\Modules\Applications\Models\Application;

it('records a self-service preference without tracking an expert-requested event', function () {
    $application = Application::factory()->create();

    app(SetApplicationAssistancePreference::class)->handle($application, AssistancePreference::SelfService);

    expect($application->fresh()->assistance_preference)->toBe(AssistancePreference::SelfService);
    expect($application->fresh()->assistance_requested_at)->toBeNull();
    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::LoanExpertRequested)->count())->toBe(0);
});

it('records an expert-assisted preference and tracks the analytics event', function () {
    $application = Application::factory()->create();

    app(SetApplicationAssistancePreference::class)->handle($application, AssistancePreference::ExpertAssisted);

    $fresh = $application->fresh();
    expect($fresh->assistance_preference)->toBe(AssistancePreference::ExpertAssisted);
    expect($fresh->assistance_requested_at)->not->toBeNull();
    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::LoanExpertRequested)->count())->toBe(1);
});

it('does not track the expert-requested event again when re-confirmed', function () {
    $application = Application::factory()->create();

    app(SetApplicationAssistancePreference::class)->handle($application, AssistancePreference::ExpertAssisted);
    app(SetApplicationAssistancePreference::class)->handle($application, AssistancePreference::ExpertAssisted);

    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::LoanExpertRequested)->count())->toBe(1);
});

it('clears the requested timestamp when switching back to self-service', function () {
    $application = Application::factory()->create();

    app(SetApplicationAssistancePreference::class)->handle($application, AssistancePreference::ExpertAssisted);
    app(SetApplicationAssistancePreference::class)->handle($application, AssistancePreference::SelfService);

    expect($application->fresh()->assistance_requested_at)->toBeNull();
});
