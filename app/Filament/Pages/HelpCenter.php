<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\AdminModules;
use App\Support\Admin\AdminGuide;
use App\Support\Admin\AdminLinks;
use App\Support\Admin\AdminWorkflows;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Help & Guides: the map of the admin (every module, the screens the signed-in
 * user can open in it, and what each is for) and step-by-step guides with live
 * progress for jobs that span several screens. Read-only; open to every admin.
 */
class HelpCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?string $navigationLabel = 'Help & Guides';

    protected static ?string $title = 'Help & Guides';

    protected static string|UnitEnum|null $navigationGroup = 'Settings & Support';

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'help';

    protected string $view = 'filament.pages.help-center';

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return array_map(
            fn (NavigationItem $item): NavigationItem => $item->extraAttributes(['data-module' => 'help']),
            parent::getNavigationItems(),
        );
    }

    /**
     * @return array<int, array{slug: string, label: string, url: ?string, icon: mixed, summary: string, when: string, screens: array<int, array{label: string, url: string, summary: string}>}>
     */
    public function modules(): array
    {
        $guides = AdminGuide::screens();
        $modules = [];

        foreach (AdminGuide::modules() as $cluster => $module) {
            $screens = collect($cluster::getClusteredComponents())
                ->sortBy(fn (string $screen): int => $screen::getNavigationSort() ?? 0)
                ->map(fn (string $screen): ?array => ($url = AdminLinks::to($screen)) ? [
                    'label' => AdminLinks::label($screen),
                    'url' => $url,
                    'summary' => $guides[$screen]['summary'] ?? '',
                ] : null)
                ->filter()
                ->values()
                ->all();

            if ($screens === []) {
                continue;
            }

            $modules[] = [
                'slug' => $cluster::getSlug(),
                'label' => $cluster::getNavigationLabel(),
                'url' => $screens[0]['url'],
                'icon' => $cluster::getNavigationIcon(),
                'summary' => $module['summary'],
                'when' => $module['when'],
                'screens' => $screens,
            ];
        }

        return $modules;
    }

    /**
     * @return array<string, array{title: string, description: string, steps: array<int, array<string, mixed>>}>
     */
    public function workflows(): array
    {
        return collect(array_keys(AdminWorkflows::all()))
            ->mapWithKeys(fn (string $key): array => [$key => AdminWorkflows::resolve($key)])
            ->all();
    }

    public function createPageUrl(): ?string
    {
        return AdminLinks::to(CreateWebsitePage::class);
    }

    /**
     * Total screens the user can open, for the intro line.
     */
    public function screenCount(): int
    {
        return collect(AdminModules::placements())
            ->keys()
            ->filter(fn (string $screen): bool => (is_subclass_of($screen, Resource::class) || is_subclass_of($screen, Page::class)) && AdminLinks::to($screen) !== null)
            ->count();
    }
}
