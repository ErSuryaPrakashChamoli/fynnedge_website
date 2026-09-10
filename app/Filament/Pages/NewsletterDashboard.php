<?php

namespace App\Filament\Pages;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The newsletter overview: list health, where subscribers come from, and which
 * articles actually convert readers into subscribers.
 *
 * Deliberately built from the newsletter's own tables only. The site has no
 * per-article view tracking, so a "conversion rate" would need a denominator
 * that does not exist — this reports signups per page instead of inventing one.
 */
class NewsletterDashboard extends Page
{
    protected string $view = 'filament.pages.newsletter-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?string $title = 'Newsletter Dashboard';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:NewsletterDashboard');
    }

    /**
     * @return array<string, array{value: int|string, description: string}>
     */
    public function stats(): array
    {
        $counts = NewsletterSubscriber::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $active = (int) $counts->get(SubscriberStatus::Active->value, 0);

        return [
            'Total subscribers' => [
                'value' => (int) $counts->sum(),
                'description' => 'Everyone who has ever signed up, including those who left.',
            ],
            'Active' => [
                'value' => $active,
                'description' => 'Confirmed and still subscribed — the only people campaigns reach.',
            ],
            'Pending confirmation' => [
                'value' => (int) $counts->get(SubscriberStatus::Pending->value, 0),
                'description' => 'Signed up but have not clicked the confirmation link yet.',
            ],
            'Unsubscribed' => [
                'value' => (int) $counts->get(SubscriberStatus::Unsubscribed->value, 0),
                'description' => 'Kept on record so they are never mailed again.',
            ],
            'New in last 30 days' => [
                'value' => NewsletterSubscriber::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'description' => 'Signups across every page on the site.',
            ],
        ];
    }

    /**
     * Twelve weeks of signups, as a simple table of buckets — enough to see a
     * trend without pulling in a charting dependency the panel doesn't have.
     *
     * @return Collection<int, array{label: string, total: int}>
     */
    public function growth(): Collection
    {
        $since = now()->subWeeks(11)->startOfWeek();

        $byWeek = NewsletterSubscriber::query()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->groupBy(fn ($subscriber): string => $subscriber->created_at->startOfWeek()->toDateString())
            ->map->count();

        return collect(range(0, 11))->map(function (int $offset) use ($since, $byWeek): array {
            $week = $since->copy()->addWeeks($offset);

            return [
                'label' => $week->format('d M'),
                'total' => (int) $byWeek->get($week->toDateString(), 0),
            ];
        });
    }

    /**
     * Which pages win subscribers. Grouped by the exact URL, so an admin can
     * see that one article outperforms the homepage.
     *
     * @return Collection<int, array{source: string, url: string, total: int}>
     */
    public function topPages(): Collection
    {
        return NewsletterSubscriber::query()
            ->selectRaw('source, source_url, count(*) as total')
            ->groupBy('source', 'source_url')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'source' => SubscriptionSource::tryFrom((string) $row->source)?->getLabel() ?? 'Unknown',
                'url' => $row->source_url ?: '—',
                'total' => (int) $row->total,
            ]);
    }

    /**
     * @return Collection<int, NewsletterCampaign>
     */
    public function recentCampaigns(): Collection
    {
        return NewsletterCampaign::query()
            ->withCount([
                'recipients',
                'recipients as opened_count' => fn ($query) => $query->whereNotNull('opened_at'),
            ])
            ->whereIn('status', [CampaignStatus::Sent, CampaignStatus::Sending])
            ->latest('sent_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    public function maxGrowth(): int
    {
        return max(1, (int) $this->growth()->max('total'));
    }
}
