<?php

use App\Models\Setting;
use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('frees requests a dead worker left behind without calling Surepass', function () {
    fakeSurepassCibil();
    $neverSent = CibilCreditRequest::factory()->create(['requested_at' => now()->subMinutes(20)]);
    $maybeSent = CibilCreditRequest::factory()->score()->processing()->create(['processing_started_at' => now()->subMinutes(20)]);
    $recent = CibilCreditRequest::factory()->processing()->create();

    $this->artisan('cibil:flag-stale')->assertSuccessful();

    expect($neverSent->refresh())->status->toBe(CibilRequestStatus::Failed)->error_code->toBe(CibilErrorCode::QueueStalled)->in_flight_key->toBeNull()
        ->and($maybeSent->refresh())->status->toBe(CibilRequestStatus::ReconciliationRequired)->in_flight_key->not->toBeNull()
        ->and($recent->refresh()->status)->toBe(CibilRequestStatus::Processing);
    Http::assertNothingSent();
});

it('purges result contents and personal details after the retention period, keeping the audit record', function () {
    Storage::fake(FetchCibilPdf::DISK);
    Storage::disk(FetchCibilPdf::DISK)->put('cibil-reports/old.pdf.enc', 'encrypted');
    Setting::set(CibilSettings::RETENTION_DAYS, 90);
    $old = CibilCreditRequest::factory()->pdf()->successful()->create(['requested_at' => now()->subDays(91), 'pdf_path' => 'cibil-reports/old.pdf.enc', 'summary' => ['score' => 761]]);
    $awaiting = CibilCreditRequest::factory()->awaitingReconciliation()->create(['requested_at' => now()->subDays(91)]);
    $recent = CibilCreditRequest::factory()->successful()->create(['requested_at' => now()->subDays(10)]);

    $this->artisan('cibil:enforce-retention')->assertSuccessful();

    expect($old->refresh())
        ->status->toBe(CibilRequestStatus::Expired)
        ->raw_response->toBeNull()->summary->toBeNull()->score->toBeNull()
        ->pan_encrypted->toBeNull()->full_name->toBeNull()->mobile->toBeNull()
        ->pan_masked->not->toBeNull()
        ->and($awaiting->refresh()->purged_at)->toBeNull()
        ->and($recent->refresh()->raw_response)->not->toBeNull();
    Storage::disk(FetchCibilPdf::DISK)->assertMissing('cibil-reports/old.pdf.enc');
});

it('reports which CIBIL APIs the token may use', function () {
    fakeSurepassCibil(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    expect(Artisan::call('cibil:check-access'))->toBe(0)
        ->and(Artisan::output())
        ->toContain('yes (SUREPASS_CIBIL_TOKEN)')
        ->toContain('Allowed — your plan includes this API')
        ->toContain('NOT ENABLED on your Surepass plan')
        ->toContain('Score-only requests will fail')
        ->toContain('PDF report requests will fail')
        ->not->toContain('test-surepass-token');
});

it('is scheduled: stale requests every five minutes, retention daily', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($event) => [$event->command, $event->expression]);

    expect($events->first(fn (array $event) => str_contains((string) $event[0], 'cibil:flag-stale'))[1])->toBe('*/5 * * * *')
        ->and($events->first(fn (array $event) => str_contains((string) $event[0], 'cibil:enforce-retention'))[1])->toBe('55 2 * * *');
});
