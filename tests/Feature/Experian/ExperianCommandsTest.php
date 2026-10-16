<?php

use App\Models\Setting;
use App\Modules\Experian\Actions\FetchExperianPdf;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('frees requests a dead worker left behind without calling Surepass', function () {
    fakeSurepassExperian();
    $neverSent = ExperianCreditRequest::factory()->create(['requested_at' => now()->subMinutes(20)]);
    $maybeSent = ExperianCreditRequest::factory()->score()->processing()->create(['processing_started_at' => now()->subMinutes(20)]);
    $recent = ExperianCreditRequest::factory()->processing()->create();

    $this->artisan('experian:flag-stale')->assertSuccessful();

    expect($neverSent->refresh())->status->toBe(ExperianRequestStatus::Failed)->error_code->toBe(ExperianErrorCode::QueueStalled)->in_flight_key->toBeNull()
        ->and($maybeSent->refresh())->status->toBe(ExperianRequestStatus::ReconciliationRequired)->in_flight_key->not->toBeNull()
        ->and($recent->refresh()->status)->toBe(ExperianRequestStatus::Processing);
    Http::assertNothingSent();
});

it('purges result contents and personal details after the retention period, keeping the audit record', function () {
    Storage::fake(FetchExperianPdf::DISK);
    Storage::disk(FetchExperianPdf::DISK)->put('experian-reports/old.pdf.enc', 'encrypted');
    Setting::set(ExperianSettings::RETENTION_DAYS, 90);
    $old = ExperianCreditRequest::factory()->pdf()->successful()->create(['requested_at' => now()->subDays(91), 'pdf_path' => 'experian-reports/old.pdf.enc', 'summary' => ['score' => 761]]);
    $awaiting = ExperianCreditRequest::factory()->awaitingReconciliation()->create(['requested_at' => now()->subDays(91)]);
    $recent = ExperianCreditRequest::factory()->successful()->create(['requested_at' => now()->subDays(10)]);

    $this->artisan('experian:enforce-retention')->assertSuccessful();

    expect($old->refresh())
        ->status->toBe(ExperianRequestStatus::Expired)
        ->raw_response->toBeNull()->summary->toBeNull()->score->toBeNull()
        ->pan_encrypted->toBeNull()->full_name->toBeNull()->mobile->toBeNull()
        ->pan_masked->not->toBeNull()
        ->and($awaiting->refresh()->purged_at)->toBeNull()
        ->and($recent->refresh()->raw_response)->not->toBeNull();
    Storage::disk(FetchExperianPdf::DISK)->assertMissing('experian-reports/old.pdf.enc');
});

it('reports which Experian APIs the token may use', function () {
    fakeSurepassExperian(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    expect(Artisan::call('experian:check-access'))->toBe(0)
        ->and(Artisan::output())
        ->toContain('yes (SUREPASS_BEARER_TOKEN, shared with CRIF)')
        ->toContain('Allowed — your plan includes this API')
        ->toContain('NOT ENABLED on your Surepass plan')
        ->toContain('Score-only requests will fail')
        ->toContain('PDF report requests will fail')
        ->not->toContain('test-surepass-token');
});

it('is scheduled: stale requests every five minutes, retention daily', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($event) => [$event->command, $event->expression]);

    expect($events->first(fn (array $event) => str_contains((string) $event[0], 'experian:flag-stale'))[1])->toBe('*/5 * * * *')
        ->and($events->first(fn (array $event) => str_contains((string) $event[0], 'experian:enforce-retention'))[1])->toBe('50 2 * * *');
});
