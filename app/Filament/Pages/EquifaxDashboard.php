<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxSettings;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The Equifax operations overview: paid score and report requests, what reuse
 * and duplicate blocking saved, what failed and why, and what waits for a
 * human (reconciliation). Built from equifax_credit_requests and the shared
 * audit log only; shows masked PANs, never customer details.
 */
class EquifaxDashboard extends Page
{
    protected string $view = 'filament.pages.equifax-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Equifax';

    protected static ?string $navigationLabel = 'Equifax Dashboard';

    protected static ?string $title = 'Equifax Dashboard';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:EquifaxDashboard');
    }

    /**
     * @return array{enabled: bool, public: bool, configured: bool, score_days: int, report_days: int, consent_days: int}
     */
    public function integration(): array
    {
        return [
            'enabled' => EquifaxSettings::enabled(),
            'public' => EquifaxSettings::publicScoreEnabled(),
            'configured' => EquifaxSettings::isConfigured(),
            'score_days' => EquifaxSettings::scoreReuseDays(),
            'report_days' => EquifaxSettings::reportReuseDays(),
            'consent_days' => EquifaxSettings::consentValidityDays(),
        ];
    }

    /**
     * @return array<string, array{value: int, description: string}>
     */
    public function stats(): array
    {
        $byStatus = EquifaxCreditRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byProduct = EquifaxCreditRequest::query()->selectRaw('product, count(*) as total')->groupBy('product')->pluck('total', 'product');
        $count = fn (EquifaxRequestStatus ...$statuses): int => (int) collect($statuses)->sum(fn (EquifaxRequestStatus $status) => $byStatus->get($status->value, 0));

        return [
            'Score requests' => ['value' => (int) $byProduct->get(EquifaxProduct::Score->value, 0), 'description' => 'Paid score-only attempts.'],
            'Full-report requests' => ['value' => (int) $byProduct->get(EquifaxProduct::Report->value, 0), 'description' => 'Paid full-report attempts.'],
            'Successful' => ['value' => $count(EquifaxRequestStatus::Success, EquifaxRequestStatus::Expired), 'description' => 'Results received (including ones since purged).'],
            'Failed' => ['value' => $count(EquifaxRequestStatus::Failed), 'description' => 'Ended without a result.'],
            'In progress' => ['value' => $count(EquifaxRequestStatus::Pending, EquifaxRequestStatus::Processing), 'description' => 'Queued or waiting for Surepass.'],
            'Needs reconciliation' => ['value' => $count(EquifaxRequestStatus::ReconciliationRequired), 'description' => 'Outcome uncertain — check Surepass, then reconcile.'],
            'Duplicates prevented' => ['value' => (int) EquifaxCreditRequest::query()->sum('duplicates_blocked'), 'description' => 'Requests blocked because an equivalent one was in progress.'],
            'Results reused' => ['value' => (int) EquifaxCreditRequest::query()->sum('times_reused'), 'description' => 'Requests answered with an existing result — no charge.'],
            'Fresh requests' => ['value' => EquifaxCreditRequest::query()->where('request_type', EquifaxRequestType::Fresh)->count(), 'description' => 'Authorised overrides of a reusable result.'],
        ];
    }

    /**
     * @return Collection<int, array{name: string, total: int, scores: int, reports: int, success: int, failed: int}>
     */
    public function byEmployee(): Collection
    {
        $rows = EquifaxCreditRequest::query()
            ->selectRaw('requested_by, status, product, count(*) as total')
            ->groupBy('requested_by', 'status', 'product')
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('requested_by')->filter())->pluck('name', 'id');

        return $rows->groupBy('requested_by')
            ->map(fn (Collection $group, $userId): array => [
                'name' => blank($userId) ? 'Website visitors (public score page)' : $names->get($userId, 'Deleted user'),
                'total' => (int) $group->sum('total'),
                'scores' => (int) $group->filter(fn ($row) => $row->product === EquifaxProduct::Score)->sum('total'),
                'reports' => (int) $group->filter(fn ($row) => $row->product === EquifaxProduct::Report)->sum('total'),
                'success' => (int) $group->filter(fn ($row) => in_array($row->status, [EquifaxRequestStatus::Success, EquifaxRequestStatus::Expired], true))->sum('total'),
                'failed' => (int) $group->filter(fn ($row) => in_array($row->status, [EquifaxRequestStatus::Failed, EquifaxRequestStatus::ReconciliationRequired], true))->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * The most recent provider errors, newest first.
     *
     * @return Collection<int, EquifaxCreditRequest>
     */
    public function recentErrors(): Collection
    {
        return EquifaxCreditRequest::query()
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
        return EquifaxCreditRequest::query()
            ->whereNotNull('error_code')
            ->selectRaw('error_code, count(*) as total')
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->error_code?->getLabel() ?? 'Unknown',
                'total' => (int) $row->total,
                'uncertain' => $row->error_code instanceof EquifaxErrorCode && $row->error_code->isUncertain(),
            ]);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(): Collection
    {
        return AuditLog::query()
            ->where('auditable_type', EquifaxCreditRequest::class)
            ->with(['user', 'auditable'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }
}
