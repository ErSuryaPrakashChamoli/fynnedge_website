<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifSettings;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The CRIF operations overview: how many paid requests were made, how many
 * were avoided by reuse and duplicate blocking, what failed and why, and what
 * is waiting for a human (reconciliation). Built from crif_reports and the
 * shared audit log only; shows masked PANs, never customer details.
 */
class CrifDashboard extends Page
{
    protected string $view = 'filament.pages.crif-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Credit Bureau';

    protected static ?string $navigationLabel = 'CRIF Dashboard';

    protected static ?string $title = 'CRIF Dashboard';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:CrifDashboard');
    }

    /**
     * @return array{enabled: bool, configured: bool, reuse_days: int, consent_days: int}
     */
    public function integration(): array
    {
        return [
            'enabled' => CrifSettings::enabled(),
            'configured' => CrifSettings::isConfigured(),
            'reuse_days' => CrifSettings::reuseDays(),
            'consent_days' => CrifSettings::consentValidityDays(),
        ];
    }

    /**
     * @return array<string, array{value: int, description: string}>
     */
    public function stats(): array
    {
        $byStatus = CrifReport::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (CrifReportStatus ...$statuses): int => (int) collect($statuses)->sum(fn (CrifReportStatus $status) => $byStatus->get($status->value, 0));

        return [
            'Total requests' => ['value' => (int) $byStatus->sum(), 'description' => 'Every paid attempt sent or queued.'],
            'Successful' => ['value' => $count(CrifReportStatus::Success, CrifReportStatus::Expired), 'description' => 'Reports received (including ones since purged).'],
            'Failed' => ['value' => $count(CrifReportStatus::Failed), 'description' => 'Ended without a report.'],
            'In progress' => ['value' => $count(CrifReportStatus::Pending, CrifReportStatus::Processing), 'description' => 'Queued or waiting for Surepass.'],
            'Needs reconciliation' => ['value' => $count(CrifReportStatus::ReconciliationRequired), 'description' => 'Outcome uncertain — check Surepass, then reconcile.'],
            'Duplicates prevented' => ['value' => (int) CrifReport::query()->sum('duplicates_blocked'), 'description' => 'Requests blocked because one for the PAN was in progress.'],
            'Reports reused' => ['value' => (int) CrifReport::query()->sum('times_reused'), 'description' => 'Requests answered with an existing report — no charge.'],
            'Fresh requests' => ['value' => CrifReport::query()->where('request_type', CrifRequestType::Fresh)->count(), 'description' => 'Authorised overrides of a reusable report.'],
        ];
    }

    /**
     * @return Collection<int, array{label: string, total: int}>
     */
    public function byDay(): Collection
    {
        $since = now()->subDays(13)->startOfDay();

        $counts = CrifReport::query()
            ->where('requested_at', '>=', $since)
            ->get(['requested_at'])
            ->groupBy(fn (CrifReport $report): string => $report->requested_at->toDateString())
            ->map->count();

        return collect(range(0, 13))->map(function (int $offset) use ($since, $counts): array {
            $day = $since->copy()->addDays($offset);

            return ['label' => $day->format('d M'), 'total' => (int) $counts->get($day->toDateString(), 0)];
        });
    }

    public function maxByDay(): int
    {
        return max(1, (int) $this->byDay()->max('total'));
    }

    /**
     * @return Collection<int, array{name: string, total: int, success: int, failed: int, fresh: int}>
     */
    public function byEmployee(): Collection
    {
        $rows = CrifReport::query()
            ->selectRaw('requested_by, status, request_type, count(*) as total')
            ->groupBy('requested_by', 'status', 'request_type')
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('requested_by')->filter())->pluck('name', 'id');

        return $rows->groupBy('requested_by')
            ->map(fn (Collection $group, $userId): array => [
                'name' => $names->get($userId, 'Deleted user'),
                'total' => (int) $group->sum('total'),
                'success' => (int) $group->filter(fn ($row) => in_array($row->status, [CrifReportStatus::Success, CrifReportStatus::Expired], true))->sum('total'),
                'failed' => (int) $group->filter(fn ($row) => in_array($row->status, [CrifReportStatus::Failed, CrifReportStatus::ReconciliationRequired], true))->sum('total'),
                'fresh' => (int) $group->filter(fn ($row) => $row->request_type === CrifRequestType::Fresh)->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return Collection<int, array{label: string, total: int, uncertain: bool}>
     */
    public function errors(): Collection
    {
        return CrifReport::query()
            ->whereNotNull('error_code')
            ->selectRaw('error_code, count(*) as total')
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->error_code?->getLabel() ?? 'Unknown',
                'total' => (int) $row->total,
                'uncertain' => $row->error_code instanceof CrifErrorCode && $row->error_code->isUncertain(),
            ]);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(): Collection
    {
        return AuditLog::query()
            ->where('auditable_type', CrifReport::class)
            ->with(['user', 'auditable'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }
}
