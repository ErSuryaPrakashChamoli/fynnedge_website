<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CibilCreditRequests\CibilCreditRequestResource;
use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Enums\CibilRequestType;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\Experian\Models\ExperianCreditRequest;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The CIBIL operations overview: paid score, report and PDF requests, what reuse
 * and duplicate blocking saved, what failed and why, and what waits for a
 * human (reconciliation). Built from cibil_credit_requests and the shared
 * audit log only; shows masked PANs, never customer details.
 */
class CibilDashboard extends Page
{
    protected string $view = 'filament.pages.cibil-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'CIBIL';

    protected static ?string $navigationLabel = 'CIBIL Dashboard';

    protected static ?string $title = 'CIBIL Dashboard';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:CibilDashboard');
    }

    /**
     * @return array{enabled: bool, public: bool, configured: bool, score_days: int, report_days: int, consent_days: int}
     */
    public function integration(): array
    {
        return [
            'enabled' => CibilSettings::enabled(),
            'public' => CibilSettings::publicScoreEnabled(),
            'configured' => CibilSettings::isConfigured(),
            'score_days' => CibilSettings::scoreReuseDays(),
            'report_days' => CibilSettings::reportReuseDays(),
            'consent_days' => CibilSettings::consentValidityDays(),
        ];
    }

    /**
     * The Free CIBIL Score page's Experian stand-in: whether it answers the
     * page now, how many full reports it produced, and where staff allowed
     * to see Experian requests find them. Those reports are not CIBIL
     * requests, so the figures below never include them.
     *
     * @return array{active: bool, reports: int, url: ?string}
     */
    public function experianStandIn(): array
    {
        $canViewExperian = (bool) auth()->user()?->can('viewAny', ExperianCreditRequest::class);

        return [
            'active' => CibilSettings::publicScoreEnabled() && CibilSettings::isStandIn(),
            'reports' => CibilCreditRequestResource::experianStandInReportCount(),
            'url' => $canViewExperian ? ExperianCreditRequestResource::getUrl('index', ['tab' => 'reports']) : null,
        ];
    }

    /**
     * @return array<string, array{value: int, description: string}>
     */
    public function stats(): array
    {
        $byStatus = CibilCreditRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byProduct = CibilCreditRequest::query()->selectRaw('product, count(*) as total')->groupBy('product')->pluck('total', 'product');
        $count = fn (CibilRequestStatus ...$statuses): int => (int) collect($statuses)->sum(fn (CibilRequestStatus $status) => $byStatus->get($status->value, 0));

        return [
            'Score requests' => ['value' => (int) $byProduct->get(CibilProduct::Score->value, 0), 'description' => 'Paid score-only attempts.'],
            'Full-report requests' => ['value' => (int) $byProduct->get(CibilProduct::Report->value, 0), 'description' => 'Paid full-report attempts.'],
            'PDF requests' => ['value' => (int) $byProduct->get(CibilProduct::Pdf->value, 0), 'description' => 'Paid PDF-report attempts.'],
            'Successful' => ['value' => $count(CibilRequestStatus::Success, CibilRequestStatus::Expired), 'description' => 'Results received (including ones since purged).'],
            'Failed' => ['value' => $count(CibilRequestStatus::Failed), 'description' => 'Ended without a result.'],
            'In progress' => ['value' => $count(CibilRequestStatus::Pending, CibilRequestStatus::Processing), 'description' => 'Queued or waiting for Surepass.'],
            'Needs reconciliation' => ['value' => $count(CibilRequestStatus::ReconciliationRequired), 'description' => 'Outcome uncertain — check Surepass, then reconcile.'],
            'Duplicates prevented' => ['value' => (int) CibilCreditRequest::query()->sum('duplicates_blocked'), 'description' => 'Requests blocked because an equivalent one was in progress.'],
            'Results reused' => ['value' => (int) CibilCreditRequest::query()->sum('times_reused'), 'description' => 'Requests answered with an existing result — no charge.'],
            'Fresh requests' => ['value' => CibilCreditRequest::query()->where('request_type', CibilRequestType::Fresh)->count(), 'description' => 'Authorised overrides of a reusable result.'],
        ];
    }

    /**
     * @return Collection<int, array{name: string, total: int, scores: int, reports: int, pdfs: int, success: int, failed: int}>
     */
    public function byEmployee(): Collection
    {
        $rows = CibilCreditRequest::query()
            ->selectRaw('requested_by, status, product, count(*) as total')
            ->groupBy('requested_by', 'status', 'product')
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('requested_by')->filter())->pluck('name', 'id');

        return $rows->groupBy('requested_by')
            ->map(fn (Collection $group, $userId): array => [
                'name' => blank($userId) ? 'Website visitors (public score page)' : $names->get($userId, 'Deleted user'),
                'total' => (int) $group->sum('total'),
                'scores' => (int) $group->filter(fn ($row) => $row->product === CibilProduct::Score)->sum('total'),
                'reports' => (int) $group->filter(fn ($row) => $row->product === CibilProduct::Report)->sum('total'),
                'pdfs' => (int) $group->filter(fn ($row) => $row->product === CibilProduct::Pdf)->sum('total'),
                'success' => (int) $group->filter(fn ($row) => in_array($row->status, [CibilRequestStatus::Success, CibilRequestStatus::Expired], true))->sum('total'),
                'failed' => (int) $group->filter(fn ($row) => in_array($row->status, [CibilRequestStatus::Failed, CibilRequestStatus::ReconciliationRequired], true))->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * The newest requests, whatever their outcome.
     *
     * @return Collection<int, CibilCreditRequest>
     */
    public function latestRequests(): Collection
    {
        return CibilCreditRequest::query()
            ->with('customer')
            ->latest('requested_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /**
     * The most recent provider errors, newest first.
     *
     * @return Collection<int, CibilCreditRequest>
     */
    public function recentErrors(): Collection
    {
        return CibilCreditRequest::query()
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
        return CibilCreditRequest::query()
            ->whereNotNull('error_code')
            ->selectRaw('error_code, count(*) as total')
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->error_code?->getLabel() ?? 'Unknown',
                'total' => (int) $row->total,
                'uncertain' => $row->error_code instanceof CibilErrorCode && $row->error_code->isUncertain(),
            ]);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(): Collection
    {
        return AuditLog::query()
            ->where('auditable_type', CibilCreditRequest::class)
            ->with(['user', 'auditable'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }
}
