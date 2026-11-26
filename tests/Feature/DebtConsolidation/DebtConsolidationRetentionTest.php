<?php

use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use Illuminate\Support\Facades\Artisan;

it('withdraws abandoned drafts and old anonymous calculator snapshots, and nothing else', function () {
    $abandoned = DebtApplication::factory()->create(['last_customer_activity_at' => now()->subDays(181)]);
    $recentDraft = DebtApplication::factory()->create(['last_customer_activity_at' => now()->subDays(10)]);
    $oldEnquiry = DebtApplication::factory()->enquiryOnly()->create(['last_customer_activity_at' => now()->subDays(400)]);
    $oldCalculator = DebtCalculationSnapshot::factory()->create(['debt_application_id' => null, 'created_at' => now()->subDays(91)]);
    $caseSnapshot = DebtCalculationSnapshot::factory()->create(['created_at' => now()->subDays(400)]);

    Artisan::call('debt-consolidation:enforce-retention');

    expect(DebtApplication::withTrashed()->find($abandoned->id))->trashed()->toBeTrue()->status->toBe(DebtApplicationStatus::Withdrawn)
        ->and($recentDraft->fresh()->trashed())->toBeFalse()
        ->and($oldEnquiry->fresh()->trashed())->toBeFalse()
        ->and(DebtCalculationSnapshot::query()->find($oldCalculator->id))->toBeNull()
        ->and($caseSnapshot->fresh())->not->toBeNull()
        ->and(Artisan::output())->toContain('Removed 1 abandoned draft(s) and 1 anonymous calculator snapshot(s).');
});

it('only reports in a dry run', function () {
    $abandoned = DebtApplication::factory()->create(['last_customer_activity_at' => now()->subDays(181)]);

    Artisan::call('debt-consolidation:enforce-retention', ['--dry-run' => true]);

    expect($abandoned->fresh()->trashed())->toBeFalse()->and(Artisan::output())->toContain('Would remove 1');
});
