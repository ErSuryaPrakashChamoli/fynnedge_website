<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CreateWebsitePage;
use App\Filament\Pages\HelpCenter;
use App\Filament\Pages\LeadInbox;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Services\LeadInbox as LeadInboxQuery;
use App\Support\Admin\AdminGuide;
use App\Support\Admin\AdminLinks;
use Filament\Widgets\Widget;

/**
 * Top of the dashboard: a greeting, the day's lead count and one-click
 * shortcuts to the jobs staff do most — each shown only when the signed-in
 * user can open it — plus the module map in brief. Replaces Filament's
 * generic account card.
 */
class WelcomeWidget extends Widget
{
    protected static ?int $sort = -10;

    /** First thing on the dashboard: render with the page, not after a second request. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome';

    /**
     * @return array{greeting: string, name: string, date: string, leadsToday: ?int, actions: array<int, array{label: string, url: string, icon: string, primary: bool}>, modules: array<int, array{label: string, url: string, icon: mixed, summary: string, module: string}>}
     */
    public function data(): array
    {
        $hour = (int) now('Asia/Kolkata')->format('G');
        $user = auth()->user();

        $actions = collect([
            ['Create a Page', AdminLinks::to(CreateWebsitePage::class), 'heroicon-m-document-plus', true],
            ['Lead Inbox', AdminLinks::to(LeadInbox::class), 'heroicon-m-inbox-stack', false],
            ['Write an article', AdminLinks::to(ArticleResource::class, create: true), 'heroicon-m-pencil-square', false],
            ['New landing page', AdminLinks::to(LoanLandingPageResource::class, create: true), 'heroicon-m-banknotes', false],
            ['Help & Guides', AdminLinks::to(HelpCenter::class), 'heroicon-m-lifebuoy', false],
        ])
            ->filter(fn (array $action): bool => $action[1] !== null)
            ->map(fn (array $action): array => ['label' => $action[0], 'url' => $action[1], 'icon' => $action[2], 'primary' => $action[3]])
            ->values()
            ->all();

        $modules = collect(AdminGuide::modules())
            ->map(function (array $module, string $cluster): ?array {
                $first = collect($cluster::getClusteredComponents())
                    ->sortBy(fn (string $screen): int => $screen::getNavigationSort() ?? 0)
                    ->map(fn (string $screen): ?string => AdminLinks::to($screen))
                    ->filter()
                    ->first();

                return $first ? ['label' => $cluster::getNavigationLabel(), 'url' => $first, 'icon' => $cluster::getNavigationIcon(), 'summary' => $module['summary'], 'module' => $cluster::getSlug()] : null;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 17 => 'Good afternoon',
                default => 'Good evening',
            },
            'name' => (string) str($user?->name ?? '')->before(' '),
            'date' => now('Asia/Kolkata')->format('l, j F Y'),
            'leadsToday' => LeadSource::accessible() === [] ? null : LeadInboxQuery::query(array_values(array_filter(LeadSource::accessible(), fn (LeadSource $source): bool => $source !== LeadSource::OtpVerification)))
                ->where('received_at', '>=', now('Asia/Kolkata')->startOfDay()->utc())
                ->count(),
            'actions' => $actions,
            'modules' => $modules,
        ];
    }
}
