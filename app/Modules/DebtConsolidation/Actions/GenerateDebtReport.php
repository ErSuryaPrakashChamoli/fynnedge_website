<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ReportType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtReport;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;
use Illuminate\Validation\ValidationException;

/**
 * Generates a FynnEdge-branded PDF report for a case and stores it encrypted
 * on the private disk with its SHA-256 and a version number per report type.
 *
 * Calculation reports are rendered ONLY from a stored snapshot (inputs,
 * results, formula and scoring versions), so the same snapshot always gives
 * the same figures; the newest assessment snapshot is used, or a fresh one
 * is taken when the case has none. Lender-application and closure reports
 * record the tracking data as it stands; the stored PDF is their record.
 * Always rendered in English (dompdf cannot shape Devanagari).
 */
class GenerateDebtReport
{
    use Localizable;

    public const DISK = 'local';

    public function __construct(
        private readonly AssessDebtApplication $assess,
        private readonly DebtCaseNotifier $notifier,
    ) {}

    public function handle(DebtApplication $application, ReportType $type, ?int $userId, bool $customerVisible = false, ?DebtCalculationSnapshot $snapshot = null): DebtReport
    {
        if ($customerVisible && $type->isInternal()) {
            throw ValidationException::withMessages(['is_customer_visible' => 'The internal advisor assessment is never released to the customer.']);
        }

        if ($snapshot !== null && $snapshot->debt_application_id !== $application->getKey()) {
            throw ValidationException::withMessages(['snapshot' => 'That calculation belongs to another case.']);
        }

        if ($type->needsSnapshot()) {
            $snapshot ??= $application->latestSnapshot ?? $this->assess->handle($application, $userId === null ? ActorType::System : ActorType::Staff, $userId);
        }

        $version = (int) $application->reports()->where('report_type', $type)->max('version') + 1;
        $generatedAt = now();

        $bytes = $this->withLocale('en', fn (): string => Pdf::loadView('debt-consolidation.reports.pdf', [
            'application' => $application->loadMissing(['financialProfile', 'liabilities.quotations', 'lenderApplications.statusChanges', 'closures.payments', 'closures.liability', 'lenderMatches.lenderProduct.lender', 'lenderMatches.lenderProduct.loanProduct', 'collaterals']),
            'type' => $type,
            'snapshot' => $snapshot,
            'version' => $version,
            'generatedAt' => $generatedAt,
            'names' => $snapshot === null ? [] : DebtPresenter::liabilityNames($snapshot->inputs['liabilities'] ?? []),
            'disclaimer' => DebtConsolidationSettings::get('general_disclaimer'),
            'calculatorDisclaimer' => DebtConsolidationSettings::get('calculator_disclaimer'),
            'suitabilityDisclaimer' => DebtConsolidationSettings::get('suitability_disclaimer'),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output());

        $path = 'debt-consolidation/'.$application->public_id.'/reports/'.$type->value.'-v'.$version.'.pdf.enc';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString($bytes));

        $report = $application->reports()->create([
            'report_type' => $type,
            'version' => $version,
            'debt_calculation_snapshot_id' => $snapshot?->getKey(),
            'formula_version' => $snapshot?->formula_version,
            'disk' => self::DISK,
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'size' => strlen($bytes),
            'is_customer_visible' => $customerVisible,
            'generated_by' => $userId,
            'generated_at' => $generatedAt,
        ]);

        $application->recordEvent('report_generated', ['report' => $report->public_id, 'type' => $type->value, 'version' => $version], $userId);

        if ($customerVisible) {
            $this->notifier->reportReleased($report);
        }

        return $report;
    }

    public function release(DebtReport $report, int $userId): DebtReport
    {
        if ($report->report_type->isInternal()) {
            throw ValidationException::withMessages(['is_customer_visible' => 'The internal advisor assessment is never released to the customer.']);
        }

        if (! $report->is_customer_visible) {
            $report->forceFill(['is_customer_visible' => true])->save();
            $report->application->recordEvent('report_released', ['report' => $report->public_id], $userId);
            $this->notifier->reportReleased($report);
        }

        return $report;
    }
}
