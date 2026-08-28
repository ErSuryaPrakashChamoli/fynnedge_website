<?php

namespace App\Filament\Pages;

use App\Models\LoanProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;

class AnalyticsDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Funnel Analytics';

    protected static ?string $title = 'Funnel Analytics';

    protected string $view = 'filament.pages.analytics-dashboard';

    public ?int $loanProductId = null;

    public string $range = '30';

    /**
     * @return array<string, string>
     */
    public function loanProductOptions(): array
    {
        return ['' => 'All products', ...LoanProduct::query()->orderBy('name')->pluck('name', 'id')->all()];
    }

    /**
     * @return array<string, string>
     */
    public function rangeOptions(): array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];
    }

    /**
     * The funnel is a fixed, ordered sequence — each stage's conversion is measured
     * against "journeys started", not the previous stage, so a reader can see overall
     * drop-off at a glance without doing the multiplication themselves.
     *
     * @return array<int, array{label: string, count: int, conversion: float}>
     */
    #[Computed]
    public function funnel(): array
    {
        $counts = [
            'Journeys started' => $this->distinctSessionCount(AnalyticsEventKey::JourneyStarted),
            'Journeys completed' => $this->distinctSessionCount(AnalyticsEventKey::JourneyCompleted),
            'Eligible with a lender' => $this->distinctSessionCount(
                AnalyticsEventKey::EligibilityEvaluated,
                fn (Builder $query) => $query->whereIn('properties->status', ['eligible', 'conditional']),
            ),
            'Lender selected' => $this->distinctSessionCount(AnalyticsEventKey::LenderSelected),
            'Documents uploaded' => $this->distinctSessionCount(AnalyticsEventKey::DocumentUploaded),
            'Application submitted' => $this->distinctSessionCount(AnalyticsEventKey::ApplicationSubmitted),
        ];

        $started = $counts['Journeys started'];

        return collect($counts)
            ->map(fn (int $count, string $label) => [
                'label' => $label,
                'count' => $count,
                'conversion' => $started > 0 ? round($count / $started * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Per-product started vs. submitted, so a multi-product funnel doesn't hide which
     * products are converting versus which are all top-of-funnel traffic.
     *
     * @return array<int, array{name: string, started: int, submitted: int, conversion: float}>
     */
    #[Computed]
    public function productBreakdown(): array
    {
        return LoanProduct::query()->orderBy('name')->get()->map(function (LoanProduct $product) {
            $base = fn () => $this->rangeFilteredQuery()->where('loan_product_id', $product->id);

            $started = (clone $base())->where('event_key', AnalyticsEventKey::JourneyStarted)->distinct()->count('journey_session_id');
            $submitted = (clone $base())->where('event_key', AnalyticsEventKey::ApplicationSubmitted)->distinct()->count('journey_session_id');

            return [
                'name' => $product->name,
                'started' => $started,
                'submitted' => $submitted,
                'conversion' => $started > 0 ? round($submitted / $started * 100, 1) : 0.0,
            ];
        })->all();
    }

    /**
     * Step-level drop-off within one product's journey — only meaningful for a single
     * selected product, since step keys aren't comparable across different journeys.
     *
     * @return array<int, array{title: string, count: int}>|null
     */
    #[Computed]
    public function stepBreakdown(): ?array
    {
        if (! $this->loanProductId) {
            return null;
        }

        $definition = JourneyDefinition::query()
            ->where('loan_product_id', $this->loanProductId)
            ->where('status', JourneyDefinitionStatus::Active)
            ->latest('version')
            ->first();

        if (! $definition) {
            return null;
        }

        $countsByStep = $this->scopedQuery()
            ->where('event_key', AnalyticsEventKey::JourneyStepCompleted)
            ->get(['journey_session_id', 'properties'])
            ->groupBy(fn (AnalyticsEvent $event) => $event->properties['step_key'] ?? null)
            ->map(fn ($events) => $events->pluck('journey_session_id')->unique()->count());

        return $definition->steps()->orderBy('order')->get()
            ->map(fn ($step) => ['title' => $step->title, 'count' => $countsByStep->get($step->key, 0)])
            ->all();
    }

    private function distinctSessionCount(AnalyticsEventKey $key, ?\Closure $modify = null): int
    {
        $query = $this->scopedQuery()->where('event_key', $key);

        if ($modify) {
            $modify($query);
        }

        return $query->distinct()->count('journey_session_id');
    }

    /**
     * Filtered by the selected loan product (if any) and the selected date range.
     */
    private function scopedQuery(): Builder
    {
        $query = $this->rangeFilteredQuery();

        if ($this->loanProductId) {
            $query->where('loan_product_id', $this->loanProductId);
        }

        return $query;
    }

    /**
     * Filtered by the selected date range only — used where the caller applies its
     * own product filter, e.g. the per-product breakdown table.
     */
    private function rangeFilteredQuery(): Builder
    {
        $query = AnalyticsEvent::query();

        if ($this->range !== 'all') {
            $query->where('created_at', '>=', now()->subDays((int) $this->range));
        }

        return $query;
    }
}
