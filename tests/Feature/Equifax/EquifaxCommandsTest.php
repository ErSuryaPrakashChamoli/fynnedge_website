<?php

use App\Models\Setting;
use App\Modules\Equifax\Actions\FetchEquifaxPdf;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('frees requests a dead worker left behind without calling Surepass', function () {
    fakeSurepassEquifax();
    $neverSent = EquifaxCreditRequest::factory()->create(['requested_at' => now()->subMinutes(20)]);
    $maybeSent = EquifaxCreditRequest::factory()->score()->processing()->create(['processing_started_at' => now()->subMinutes(20)]);
    $recent = EquifaxCreditRequest::factory()->processing()->create();

    $this->artisan('equifax:flag-stale')->assertSuccessful();

    expect($neverSent->refresh())->status->toBe(EquifaxRequestStatus::Failed)->error_code->toBe(EquifaxErrorCode::QueueStalled)->in_flight_key->toBeNull()
        ->and($maybeSent->refresh())->status->toBe(EquifaxRequestStatus::ReconciliationRequired)->in_flight_key->not->toBeNull()
        ->and($recent->refresh()->status)->toBe(EquifaxRequestStatus::Processing);
    Http::assertNothingSent();
});

it('purges result contents and personal details after the retention period, keeping the audit record', function () {
    Storage::fake(FetchEquifaxPdf::DISK);
    Storage::disk(FetchEquifaxPdf::DISK)->put('equifax-reports/old.pdf.enc', 'encrypted');
    Setting::set(EquifaxSettings::RETENTION_DAYS, 90);
    $old = EquifaxCreditRequest::factory()->successful()->create(['requested_at' => now()->subDays(91), 'pdf_path' => 'equifax-reports/old.pdf.enc', 'summary' => ['score' => 761]]);
    $awaiting = EquifaxCreditRequest::factory()->awaitingReconciliation()->create(['requested_at' => now()->subDays(91)]);
    $recent = EquifaxCreditRequest::factory()->successful()->create(['requested_at' => now()->subDays(10)]);

    $this->artisan('equifax:enforce-retention')->assertSuccessful();

    expect($old->refresh())
        ->status->toBe(EquifaxRequestStatus::Expired)
        ->raw_response->toBeNull()->summary->toBeNull()->score->toBeNull()
        ->pan_encrypted->toBeNull()->full_name->toBeNull()->mobile->toBeNull()
        ->pan_masked->not->toBeNull()
        ->and($awaiting->refresh()->purged_at)->toBeNull()
        ->and($recent->refresh()->raw_response)->not->toBeNull();
    Storage::disk(FetchEquifaxPdf::DISK)->assertMissing('equifax-reports/old.pdf.enc');
});

it('reports which Equifax APIs the token may use', function () {
    fakeSurepassEquifax(fn (Request $request) => str_ends_with($request->url(), '/fetch-report')
        ? Http::response(['message' => 'Input payload validation failed'], 400)
        : Http::response(['success' => false, 'message' => 'Your access token is not valid for this API/Scope.'], 401));

    expect(Artisan::call('equifax:check-access'))->toBe(0)
        ->and(Artisan::output())
        ->toContain('Allowed — your plan includes this API')
        ->toContain('NOT ENABLED on your Surepass plan')
        ->toContain('Score-only requests will fail');
});
