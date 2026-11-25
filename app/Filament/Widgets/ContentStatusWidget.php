<?php

namespace App\Filament\Widgets;

use App\Enums\PublishStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Article;
use App\Models\LoanLandingPage;
use App\Models\Page;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use App\Support\Admin\AdminLinks;
use Filament\Widgets\Widget;

/**
 * Website content at a glance — live vs draft per page type as a bar, then
 * the mailing list and campaigns. Each row appears only for users who can
 * open that screen.
 */
class ContentStatusWidget extends Widget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.content-status';

    public static function canView(): bool
    {
        return collect([PageResource::class, LoanLandingPageResource::class, ArticleResource::class, NewsletterSubscriberResource::class, NewsletterCampaignResource::class])
            ->contains(fn (string $resource): bool => AdminLinks::to($resource) !== null);
    }

    /**
     * @return array{pages: array<int, array{label: string, icon: string, live: int, draft: int, url: string}>, newsletter: array<int, array{label: string, icon: string, value: string, note: string, url: string}>}
     */
    public function content(): array
    {
        $pages = [];
        $newsletter = [];

        if ($url = AdminLinks::to(PageResource::class)) {
            $pages[] = ['label' => 'General pages', 'icon' => 'heroicon-o-document-text', 'live' => Page::query()->published()->count(), 'draft' => Page::query()->where('status', PublishStatus::Draft)->count(), 'url' => $url];
        }

        if ($url = AdminLinks::to(LoanLandingPageResource::class)) {
            $pages[] = ['label' => 'Loan landing pages', 'icon' => 'heroicon-o-banknotes', 'live' => LoanLandingPage::query()->published()->count(), 'draft' => LoanLandingPage::query()->where('status', PublishStatus::Draft)->count(), 'url' => $url];
        }

        if ($url = AdminLinks::to(ArticleResource::class)) {
            $pages[] = ['label' => 'Articles', 'icon' => 'heroicon-o-newspaper', 'live' => Article::query()->published()->count(), 'draft' => Article::query()->where('status', PublishStatus::Draft)->count(), 'url' => $url];
        }

        if ($url = AdminLinks::to(NewsletterSubscriberResource::class)) {
            $newsletter[] = [
                'label' => 'Newsletter subscribers',
                'icon' => 'heroicon-o-envelope',
                'value' => number_format(NewsletterSubscriber::query()->where('status', SubscriberStatus::Active)->count()),
                'note' => NewsletterSubscriber::query()->where('status', SubscriberStatus::Pending)->count().' awaiting confirmation',
                'url' => $url,
            ];
        }

        if ($url = AdminLinks::to(NewsletterCampaignResource::class)) {
            $newsletter[] = [
                'label' => 'Campaigns',
                'icon' => 'heroicon-o-paper-airplane',
                'value' => NewsletterCampaign::query()->whereIn('status', [CampaignStatus::Scheduled, CampaignStatus::Sending])->count().' scheduled',
                'note' => NewsletterCampaign::query()->where('status', CampaignStatus::Sent)->count().' sent',
                'url' => $url,
            ];
        }

        return ['pages' => $pages, 'newsletter' => $newsletter];
    }
}
