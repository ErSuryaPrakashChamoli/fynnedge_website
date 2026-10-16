<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Experian\Enums\ExperianErrorCode;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianSettings;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The Experian operations overview: paid score, report and PDF requests, what reuse
 * and duplicate blocking saved, what failed and why, and what waits for a
 * human (reconciliation). Built from experian_credit_requests and the shared
 * audit log only; shows masked PANs, never customer details.
 */
class ExperianDashboard extends Page
{
    protected string $view = 'filament.pages.experian-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Experian';

    protected static ?string $navigationLabel = 'Experian Dashboard';

    protected static ?string $title = 'Experian Dashboard';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:ExperianDashboard');
    }

    /**
     * @return array{enabled: bool, public: bool, configured: bool, score_days: int, report_days: int, consent_days: int}
     */
    public function integration(): array
    {
        return [
            'enabled' => ExperianSettings::enabled(),
            'public' => ExperianSettings::publicScoreEnabled(),
            'configured' => ExperianSettings::isConfigured(),
            'score_days' => ExperianSettings::scoreReuseDays(),
            'report_days' => ExperianSettings::reportReuseDays(),
            'consent_days' => ExperianSettings::consentValidityDays(),
        ];
    }

    /**
     * @return array<string, array{value: int, description: string}>
     */
    public function stats(): array
    {
        $byStatus = ExperianCreditRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byProduct = ExperianCreditRequest::query()->selectRaw('product, count(*) as total')->groupBy('product')->pluck('total', 'product');
        $count = fn (ExperianRequestStatus ...$statuses): int => (int) collect($statuses)->sum(fn (ExperianRequestStatus $status) => $byStatus->get($status->value, 0));

        return [
            'Score requests' => ['value' => (int) $byProduct->get(ExperianProduct::Score->value, 0), 'description' => 'Paid score-only attempts.'],
            'Full-report requests' => ['value' => (int) $byProduct->get(ExperianProduct::Report->value, 0), 'description' => 'Paid full-report attempts.'],
            'PDF requests' => ['value' => (int) $byProduct->get(ExperianProduct::Pdf->value, 0), 'description' => 'Paid PDF-report attempts.'],
            'Successful' => ['value' => $count(ExperianRequestStatus::Success, ExperianRequestStatus::Expired), 'description' => 'Results received (including ones since purged).'],
            'Failed' => ['value' => $count(ExperianRequestStatus::Failed), 'description' => 'Ended without a result.'],
            'In progress' => ['value' => $count(ExperianRequestStatus::Pending, ExperianRequestStatus::Processing), 'description' => 'Queued or waiting for Surepass.'],
            'Needs reconciliation' => ['value' => $count(ExperianRequestStatus::ReconciliationRequired), 'description' => 'Outcome uncertain — check Surepass, then reconcile.'],
            'Duplicates prevented' => ['value' => (int) ExperianCreditRequest::query()->sum('duplicates_blocked'), 'description' => 'Requests blocked because an equivalent one was in progress.'],
            'Results reused' => ['value' => (int) ExperianCreditRequest::query()->sum('times_reused'), 'description' => 'Requests answered with an existing result — no charge.'],
            'Fresh requests' => ['value' => ExperianCreditRequest::query()->where('request_type', ExperianRequestType::Fresh)->count(), 'description' => 'Authorised overrides of a reusable result.'],
        ];
    }

    /**
     * @return Collection<int, array{name: string, total: int, scores: int, reports: int, pdfs: int, success: int, failed: int}>
     */
    public function byEmployee(): Collection
    {
        $rows = ExperianCreditRequest::query()
            ->selectRaw('requested_by, status, product, count(*) as total')
            ->groupBy('requested_by', 'status', 'product')
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('requested_by')->filter())->pluck('name', 'id');

        return $rows->groupBy('requested_by')
            ->map(fn (Collection $group, $userId): array => [
                'name' => blank($userId) ? 'Website visitors (public score page)' : $names->get($userId, 'Deleted user'),
                'total' => (int) $group->sum('total'),
                'scores' => (int) $group->filter(fn ($row) => $row->product === ExperianProduct::Score)->sum('total'),
                'reports' => (int) $group->filter(fn ($row) => $row->product === ExperianProduct::Report)->sum('total'),
                'pdfs' => (int) $group->filter(fn ($row) => $row->product === ExperianProduct::Pdf)->sum('total'),
                'success' => (int) $group->filter(fn ($row) => in_array($row->status, [ExperianRequestStatus::Success, ExperianRequestStatus::Expired], true))->sum('total'),
                'failed' => (int) $group->filter(fn ($row) => in_array($row->status, [ExperianRequestStatus::Failed, ExperianRequestStatus::ReconciliationRequired], true))->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * The newest requests, whatever their outcome.
     *
     * @return Collection<int, ExperianCreditRequest>
     */
    public function latestRequests(): Collection
    {
        return ExperianCreditRequest::query()
            ->with('customer')
            ->latest('requested_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /**
     * The most recent provider errors, newest first.
     *
     * @return Collection<int, ExperianCreditRequest>
     */
    public function recentErrors(): Collection
    {
        return ExperianCreditRequest::query()
            ->whereNotNull('error_code')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, array{label: string, total: int, uncertain: bool}>
     */
    public function errors(): Collection
    {
        return ExperianCreditRequest::query()
            ->whereNotNull('error_code')
            ->selectRaw('error_code, count(*) as total')
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->error_code?->getLabel() ?? 'Unknown',
                'total' => (int) $row->total,
                'uncertain' => $row->error_code instanceof ExperianErrorCode && $row->error_code->isUncertain(),
            ]);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(): Collection
    {
        return AuditLog::query()
            ->where('auditable_type', ExperianCreditRequest::class)
            ->with(['user', 'auditable'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }
}
