<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtForeclosureQuotation;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Models\DebtNote;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Support\Formatting\IndianNumberFormatter;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Debt Consolidation operations at a glance: the pipeline from enquiry to
 * completed closure, what is due today, and quotations about to expire.
 * Counts only; no customer details beyond case references.
 */
class DebtConsolidationDashboard extends Page
{
    use BelongsToAdminModule;

    protected string $view = 'filament.debt-consolidation.dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Debt Consolidation';

    protected static ?string $slug = 'debt-consolidation-dashboard';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:DebtConsolidationDashboard');
    }

    public function serviceLive(): bool
    {
        return DebtConsolidationSettings::enabled();
    }

    /**
     * @return array<string, array{value: string, description: string, tone: string}>
     */
    public function kpis(): array
    {
        $cases = DebtApplication::query();
        $open = fn (): Builder => DebtApplication::query()->open();
        $latest = DebtCalculationSnapshot::query()->where('kind', DebtCalculationSnapshot::KIND_ASSESSMENT)->selectRaw('max(id)')->whereNotNull('debt_application_id')->groupBy('debt_application_id');
        $latestSnapshots = DebtCalculationSnapshot::query()->whereIn('id', $latest);
        $lenderSubmitted = DebtLenderApplication::query()->whereNotNull('submitted_at');
        $approved = DebtLenderApplication::query()->whereIn('status', [LenderApplicationStatus::Approved, LenderApplicationStatus::SanctionLetterIssued, LenderApplicationStatus::Disbursed]);
        $disbursed = DebtLenderApplication::query()->where('status', LenderApplicationStatus::Disbursed);

        return [
            'Total enquiries' => ['value' => number_format((clone $cases)->where('status', '!=', DebtApplicationStatus::Draft)->count()), 'description' => 'Cases received (excluding unfinished drafts)', 'tone' => 'primary'],
            'New enquiries' => ['value' => number_format((clone $cases)->where('status', DebtApplicationStatus::EnquiryReceived)->count()), 'description' => 'Waiting for first contact', 'tone' => 'warning'],
            'Applications in progress' => ['value' => number_format($open()->whereNotNull('submitted_at')->count()), 'description' => 'Submitted assessments still open', 'tone' => 'info'],
            'Individual / business' => ['value' => number_format((clone $cases)->where('applicant_type', ApplicantType::Individual)->count()).' / '.number_format((clone $cases)->where('applicant_type', ApplicantType::Business)->count()), 'description' => 'All cases by applicant type', 'tone' => 'gray'],
            'Total debt assessed' => ['value' => '₹'.IndianNumberFormatter::compact((float) (clone $latestSnapshots)->sum('total_outstanding')), 'description' => 'Latest assessment of every case', 'tone' => 'primary'],
            'Under assessment' => ['value' => number_format((clone $cases)->where('status', DebtApplicationStatus::AssessmentInProgress)->count()), 'description' => 'Status "Assessment in progress"', 'tone' => 'info'],
            'Potentially suitable' => ['value' => number_format((clone $latestSnapshots)->where('suitability_band', SuitabilityBand::PotentiallySuitable)->count()), 'description' => 'Latest assessment band (not an approval)', 'tone' => 'success'],
            'Lender submissions' => ['value' => number_format((clone $lenderSubmitted)->count()), 'description' => 'Applications submitted to lenders', 'tone' => 'info'],
            'Lender-confirmed approvals' => ['value' => number_format((clone $approved)->count()), 'description' => 'Approved, sanctioned or disbursed — as recorded from the lender', 'tone' => 'success'],
            'Disbursements' => ['value' => number_format((clone $disbursed)->count()), 'description' => '₹'.IndianNumberFormatter::compact((float) (clone $disbursed)->sum('disbursed_amount')).' disbursed by lenders', 'tone' => 'success'],
            'Closure pending' => ['value' => number_format(DebtClosure::query()->whereNotIn('status', [ClosureStatus::ClosureVerified, ClosureStatus::Cancelled])->count()), 'description' => 'Existing loans not yet verified closed', 'tone' => 'warning'],
            'Completed cases' => ['value' => number_format((clone $cases)->where('status', DebtApplicationStatus::Completed)->count()), 'description' => 'Every closure verified', 'tone' => 'success'],
        ];
    }

    /**
     * @return Collection<int, DebtNote>
     */
    public function followUps(): Collection
    {
        return DebtNote::query()->dueFollowUps()->with('application')->orderBy('follow_up_at')->limit(10)->get();
    }

    /**
     * @return Collection<int, DebtApplication>
     */
    public function caseFollowUps(): Collection
    {
        return DebtApplication::query()->open()->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay())->orderBy('follow_up_at')->limit(10)->get();
    }

    /**
     * @return Collection<int, DebtForeclosureQuotation>
     */
    public function expiringQuotations(): Collection
    {
        return DebtForeclosureQuotation::query()
            ->with('liability.application')
            ->whereDate('valid_until', '<=', now()->addDays(3))
            ->whereDate('valid_until', '>=', now()->subDays(7))
            ->whereHas('liability.closure', fn (Builder $query) => $query->whereNotIn('status', [ClosureStatus::ClosureVerified, ClosureStatus::Cancelled]))
            ->orderBy('valid_until')
            ->limit(10)
            ->get();
    }

    public function unverifiedQuotationCount(): int
    {
        return DebtForeclosureQuotation::query()->where('verification_status', VerificationStatus::Unverified)->count();
    }
}
