<?php

namespace App\Filament\Widgets;

use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\Leads\Services\LeadInbox as LeadInboxQuery;
use Filament\Widgets\ChartWidget;

/**
 * Leads per day for the last 30 days (India time), one smooth line per
 * source with a soft fill underneath.
 */
class LeadTrendChart extends ChartWidget
{
    protected static ?int $sort = -4;

    protected ?string $heading = 'Leads per day';

    protected ?string $description = 'The last 30 days, by where the lead came from.';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return LeadSource::accessible() !== [];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $sources = array_values(array_filter(LeadSource::accessible(), fn (LeadSource $source): bool => $source !== LeadSource::OtpVerification));
        $days = collect(range(29, 0))->map(fn (int $daysAgo) => now('Asia/Kolkata')->subDays($daysAgo)->startOfDay());

        $leads = $sources === [] ? collect() : LeadInboxQuery::query($sources)
            ->where('received_at', '>=', $days->first()->copy()->utc())
            ->get(['source', 'received_at'])
            ->groupBy(fn (LeadInboxEntry $lead): string => $lead->source->value.'|'.$lead->received_at->timezone('Asia/Kolkata')->format('Y-m-d'));

        $palette = [
            LeadSource::Enquiry->value => '#1E3A5F',
            LeadSource::CreditScoreCheck->value => '#0EA5E9',
            LeadSource::OnlineApplication->value => '#F59E0B',
            LeadSource::LoanApplication->value => '#10B981',
        ];

        return [
            'datasets' => collect($sources)->map(fn (LeadSource $source): array => [
                'label' => $source->getLabel(),
                'data' => $days->map(fn ($day): int => $leads->get($source->value.'|'.$day->format('Y-m-d'))?->count() ?? 0)->all(),
                'borderColor' => $palette[$source->value] ?? '#94A3B8',
                'backgroundColor' => ($palette[$source->value] ?? '#94A3B8').'1F',
                'fill' => 'origin',
                'tension' => 0.4,
                'borderWidth' => 2.5,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'pointHoverBackgroundColor' => '#FFFFFF',
                'pointHoverBorderWidth' => 2.5,
            ])->all(),
            'labels' => $days->map(fn ($day): string => $day->format('d M'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'pointStyle' => 'circle', 'boxWidth' => 8, 'boxHeight' => 8, 'padding' => 16]],
                'tooltip' => ['padding' => 10, 'cornerRadius' => 10, 'boxPadding' => 4, 'usePointStyle' => true],
            ],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8, 'color' => '#94A3B8']],
                'y' => ['beginAtZero' => true, 'border' => ['display' => false], 'grid' => ['color' => 'rgba(148, 163, 184, 0.18)'], 'ticks' => ['precision' => 0, 'color' => '#94A3B8', 'maxTicksLimit' => 5]],
            ],
        ];
    }
}
