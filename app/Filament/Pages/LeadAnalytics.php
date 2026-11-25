<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Services\LeadAnalytics as LeadAnalyticsReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leads & Enquiries → Lead Analytics: which pages, forms, products and
 * campaigns bring leads, how many verify their number and reach FynnOn LMS,
 * with the previous period for comparison. Only sources the user can open
 * are counted. All figures come from LeadAnalytics, which never estimates.
 */
class LeadAnalytics extends Page
{
    use BelongsToAdminModule;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Lead Analytics';

    protected static ?string $title = 'Lead Analytics';

    protected static ?string $slug = 'lead-analytics';

    protected string $view = 'filament.pages.lead-analytics';

    #[Url]
    public string $range = '30';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url]
    public ?string $loanProductId = null;

    #[Url]
    public ?string $source = null;

    #[Url]
    public ?string $campaign = null;

    #[Url]
    public string $granularity = 'day';

    public static function canAccess(): bool
    {
        return LeadSource::accessible() !== [];
    }

    /**
     * @return array<string, string>
     */
    public function rangeOptions(): array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months', 'custom' => 'Custom dates'];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(): array
    {
        $now = CarbonImmutable::now('Asia/Kolkata');

        if ($this->range === 'custom' && $this->from && $this->until) {
            $from = CarbonImmutable::parse($this->from, 'Asia/Kolkata')->startOfDay();
            $until = CarbonImmutable::parse($this->until, 'Asia/Kolkata')->endOfDay();

            return $from <= $until ? [$from, $until] : [$until->startOfDay(), $from->endOfDay()];
        }

        $days = in_array($this->range, ['7', '30', '90', '365'], true) ? (int) $this->range : 30;

        return [$now->subDays($days - 1)->startOfDay(), $now->endOfDay()];
    }

    /**
     * @return array<int, LeadSource>
     */
    public function sources(): array
    {
        $accessible = LeadSource::accessible();
        $chosen = LeadSource::tryFrom((string) $this->source);

        return $chosen !== null && in_array($chosen, $accessible, true) ? [$chosen] : $accessible;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function report(): array
    {
        [$from, $until] = $this->period();

        return LeadAnalyticsReport::report(
            $from,
            $until,
            $this->sources(),
            filled($this->loanProductId) ? (int) $this->loanProductId : null,
            filled($this->campaign) ? $this->campaign : null,
            in_array($this->granularity, ['day', 'week', 'month'], true) ? $this->granularity : 'day',
        );
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptions(): array
    {
        return collect(LeadSource::accessible())->mapWithKeys(fn (LeadSource $source): array => [$source->value => $source->getLabel()])->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function productOptions(): array
    {
        return LeadAnalyticsReport::productOptions();
    }

    /**
     * @return array<int, string>
     */
    public function campaignOptions(): array
    {
        return LeadAnalyticsReport::campaignOptions();
    }

    public function periodLabel(): string
    {
        [$from, $until] = $this->period();

        return $from->format('d M Y').' – '.$until->format('d M Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPages')
                ->label('Download page report (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $rows = LeadAnalyticsReport::pageRows($this->report['pages']);
                    $filename = 'lead-analytics-pages-'.now('Asia/Kolkata')->format('Y-m-d').'.csv';

                    return response()->streamDownload(function () use ($rows): void {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Page', 'Views', 'Leads', 'Mobile verified', 'Verified %', 'Delivered to LMS', 'LMS failed', 'Conversion (leads ÷ views)']);

                        foreach ($rows as $row) {
                            fputcsv($handle, array_map(fn (mixed $value): mixed => is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value, $row));
                        }

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }

    public function updated(string $property): void
    {
        unset($this->report);
    }
}
