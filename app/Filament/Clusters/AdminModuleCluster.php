<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Panel;
use Filament\Resources\Resource;

use function Filament\Support\original_request;

/**
 * A main module of the admin panel: one item in the left sidebar, with its
 * sub-modules rendered as tabs across the top of every screen inside it.
 *
 * Membership is declared in {@see AdminModules}, not on each resource, so the
 * whole information architecture can be read (and tested) in one place.
 *
 * URLs and route names deliberately do NOT change when a screen joins a
 * module: Filament would normally prefix both with the cluster slug
 * (/admin/faqs → /admin/content/faqs), which breaks every bookmark, every
 * link in an email or doc, and the test suite. The two prepend* overrides
 * keep /admin/faqs and `filament.admin.resources.faqs.*` exactly as they were;
 * only the module's own landing URL (/admin/{slug}, which redirects to its
 * first sub-module) is new.
 */
abstract class AdminModuleCluster extends Cluster
{
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function prependClusterSlug(Panel $panel, string $slug): string
    {
        return $slug;
    }

    public static function prependClusterRouteBaseName(Panel $panel, string $name): string
    {
        return $name;
    }

    /**
     * The sidebar item stays highlighted on every screen of the module.
     * Filament's default (`{cluster route}.*`) relies on the URL prefix this
     * class removes, so the active check lists the members' routes instead.
     *
     * `data-module` lets the admin theme give each module its own accent
     * colour (resources/css/filament/admin/theme.css).
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $patterns = static::memberRoutePatterns();

        return array_map(
            fn (NavigationItem $item): NavigationItem => $item
                ->isActiveWhen(fn (): bool => original_request()->routeIs($patterns))
                ->extraAttributes(['data-module' => static::getSlug()]),
            parent::getNavigationItems(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function memberRoutePatterns(): array
    {
        $patterns = [static::getRouteName()];

        foreach (static::getClusteredComponents() as $component) {
            $patterns[] = is_subclass_of($component, Resource::class)
                ? $component::getRouteBaseName().'.*'
                : $component::getRouteName();
        }

        return $patterns;
    }
}
