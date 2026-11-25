<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\FynnOnSync;
use App\Filament\Pages\LeadAnalytics;
use App\Filament\Pages\LeadInbox;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\FynnOn\Services\FynnOnSettings;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\Leads\Services\LeadInbox as LeadInboxQuery;
use App\Support\Admin\AdminLinks;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Lead operations at a glance (sources the user can see; OTP rows excluded
 * from lead counts), drawn as one "pulse" band: today, the last 7 days with
 * a daily sparkline, mobile verification as a progress bar, and FynnOn LMS
 * delivery for the last 30 days.
 */
class LeadStatsWidget extends Widget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.lead-stats';

    public static function canView(): bool
    {
        return LeadSource::accessible() !== [];
    }

    /**
     * @return array{today: int, week: int, daily: array<int, int>, days: array<int, string>, total: int, verified: int, verifiedPercent: ?int, lmsConnected: bool, delivered: int, abandoned: int, inboxUrl: ?string, analyticsUrl: ?string, syncUrl: ?string}
     */
    public function metrics(): array
    {
        $sources = array_values(array_filter(LeadSource::accessible(), fn (LeadSource $source): bool => $source !== LeadSource::OtpVerification));
        $since = now('Asia/Kolkata')->subDays(29)->startOfDay();

        /** @var Collection<int, LeadInboxEntry> $leads */
        $leads = $sources === [] ? collect() : LeadInboxQuery::query($sources)
            ->where('received_at', '>=', $since->copy()->utc())
            ->get(['source', 'received_at', 'verified_at', 'lms_status']);

        $today = now('Asia/Kolkata')->startOfDay();
        $weekStart = now('Asia/Kolkata')->subDays(6)->startOfDay();
        $days = collect(range(6, 0))->map(fn (int $daysAgo) => now('Asia/Kolkata')->subDays($daysAgo));
        $daily = $days->map(fn ($day): int => $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->received_at->timezone('Asia/Kolkata')->isSameDay($day))->count())->all();
        $verified = $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->verified_at !== null)->count();

        return [
            'today' => $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->received_at >= $today)->count(),
            'week' => $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->received_at >= $weekStart)->count(),
            'daily' => $daily,
            'days' => $days->map(fn ($day): string => $day->format('D j M'))->all(),
            'total' => $leads->count(),
            'verified' => $verified,
            'verifiedPercent' => $leads->isEmpty() ? null : (int) round($verified / $leads->count() * 100),
            'lmsConnected' => FynnOnSettings::isConfigured(),
            'delivered' => $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->syncStatus()->isDelivered())->count(),
            'abandoned' => $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->syncStatus() === FynnOnSyncStatus::Abandoned)->count(),
            'inboxUrl' => AdminLinks::to(LeadInbox::class),
            'analyticsUrl' => AdminLinks::to(LeadAnalytics::class),
            'syncUrl' => AdminLinks::to(FynnOnSync::class),
        ];
    }

    /**
     * SVG path data for the 7-day sparkline, in a 120 × 36 box.
     *
     * @param  array<int, int>  $values
     * @return array{line: string, area: string}
     */
    public static function sparkline(array $values): array
    {
        $max = max(1, ...$values);
        $step = 120 / max(1, count($values) - 1);
        $points = collect($values)->values()->map(fn (int $value, int $index): string => round($index * $step, 1).','.round(32 - ($value / $max) * 28, 1));

        return [
            'line' => 'M'.$points->implode(' L'),
            'area' => 'M0,36 L'.$points->implode(' L').' L120,36 Z',
        ];
    }
}
