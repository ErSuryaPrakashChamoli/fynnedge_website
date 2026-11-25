<?php

namespace App\Filament\Widgets;

use App\Enums\PublishStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\Page;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * The most recently changed pages, landing pages and articles — what the
 * team has been working on, with a link straight back into each editor.
 */
class RecentContentWidget extends Widget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-content';

    public static function canView(): bool
    {
        return PageResource::canAccess() || LoanLandingPageResource::canAccess() || ArticleResource::canAccess();
    }

    /**
     * @return array<int, array{type: string, title: string, status: string, live: bool, updated: string, url: ?string}>
     */
    public function items(): array
    {
        $sources = [
            [PageResource::class, Page::class, 'General page'],
            [LoanLandingPageResource::class, LoanLandingPage::class, 'Landing page'],
            [ArticleResource::class, Article::class, 'Article'],
        ];

        return collect($sources)
            ->filter(fn (array $source): bool => $source[0]::canAccess())
            ->flatMap(fn (array $source) => $source[1]::query()->latest('updated_at')->limit(8)->get()->map(fn (Model $record): array => [
                'type' => $source[2],
                'title' => (string) $record->getAttribute('title'),
                'status' => $record->getAttribute('status') instanceof PublishStatus ? $record->getAttribute('status')->getLabel() : (string) $record->getAttribute('status'),
                'live' => method_exists($record, 'isCurrentlyPublished') && $record->isCurrentlyPublished(),
                'updated_at' => $record->getAttribute('updated_at'),
                'url' => $source[0]::hasPage('edit') && $source[0]::canEdit($record) ? $source[0]::getUrl('edit', ['record' => $record]) : null,
            ]))
            ->sortByDesc('updated_at')
            ->take(8)
            ->map(fn (array $item): array => [...$item, 'updated' => $item['updated_at']?->diffForHumans() ?? '—'])
            ->values()
            ->all();
    }
}
