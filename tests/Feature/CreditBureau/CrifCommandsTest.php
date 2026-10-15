<?php

use App\Models\Setting;
use App\Modules\CreditBureau\Actions\FetchCrifReportPdf;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('frees requests a dead worker left behind without calling Surepass', function () {
    fakeSurepassCrif();
    $neverSent = CrifReport::factory()->create(['requested_at' => now()->subMinutes(20)]);
    $maybeSent = CrifReport::factory()->processing()->create(['processing_started_at' => now()->subMinutes(20)]);
    $recent = CrifReport::factory()->processing()->create();

    $this->artisan('crif:flag-stale')->assertSuccessful();

    expect($neverSent->refresh())->status->toBe(CrifReportStatus::Failed)->error_code->toBe(CrifErrorCode::QueueStalled)->retryable->toBeTrue()->in_flight_key->toBeNull()
        ->and($maybeSent->refresh())->status->toBe(CrifReportStatus::ReconciliationRequired)->error_code->toBe(CrifErrorCode::ProcessingStalled)->in_flight_key->not->toBeNull()
        ->and($recent->refresh()->status)->toBe(CrifReportStatus::Processing);
    Http::assertNothingSent();
});

it('purges report contents and personal details after the retention period', function () {
    Storage::fake(FetchCrifReportPdf::DISK);
    Storage::disk(FetchCrifReportPdf::DISK)->put('crif-reports/old.pdf.enc', 'encrypted');
    Setting::set(CrifSettings::RETENTION_DAYS, 90);
    $old = CrifReport::factory()->successful()->create(['requested_at' => now()->subDays(91), 'pdf_path' => 'crif-reports/old.pdf.enc', 'middle_name' => 'Prakash']);
    $oldFailure = CrifReport::factory()->failed()->create(['requested_at' => now()->subDays(91)]);
    $awaiting = CrifReport::factory()->awaitingReconciliation()->create(['requested_at' => now()->subDays(91)]);
    $recent = CrifReport::factory()->successful()->create(['requested_at' => now()->subDays(89)]);

    $this->artisan('crif:enforce-retention')->assertSuccessful();

    expect($old->refresh())
        ->status->toBe(CrifReportStatus::Expired)
        ->raw_response->toBeNull()
        ->score->toBeNull()
        ->pan_encrypted->toBeNull()
        ->first_name->toBeNull()
        ->middle_name->toBeNull()
        ->mobile->toBeNull()
        ->pdf_path->toBeNull()
        ->purged_at->not->toBeNull()
        ->pan_masked->not->toBeNull()
        ->and($oldFailure->refresh())->status->toBe(CrifReportStatus::Failed)->pan_encrypted->toBeNull()
        ->and($awaiting->refresh()->purged_at)->toBeNull()
        ->and($recent->refresh()->raw_response)->not->toBeNull();
    Storage::disk(FetchCrifReportPdf::DISK)->assertMissing('crif-reports/old.pdf.enc');
});

it('never keeps reports for less than the reuse period', function () {
    Setting::set(CrifSettings::REUSE_DAYS, 60);
    Setting::set(CrifSettings::RETENTION_DAYS, 30);

    expect(CrifSettings::retentionDays())->toBe(60);
});
