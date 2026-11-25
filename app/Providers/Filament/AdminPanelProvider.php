<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SecurityHeaders;
use App\Support\Admin\AdminHelpPanels;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\ViteException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->passwordReset()
            ->profile()
            // isRequired is intentionally left at its default (false): Filament's
            // isRequired accepts a Closure, but it is evaluated while routes/middleware
            // are being registered — before session-based auth resolves the current
            // user — so a role-based closure (e.g. requiring MFA only for super_admin)
            // always sees a null user and silently never enforces for anyone. Verified
            // empirically, not assumed: a static `isRequired: true` correctly redirects
            // every user to the MFA setup page, but a per-role closure redirects no one.
            // See .ai/rules and the Phase 5 report for the full investigation. Per-role
            // enforcement was therefore NOT implemented — building a custom middleware
            // to work around this was explicitly out of scope.
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            /*
             * Brand navy (#1E3A5F) is shade 800. The shades are spelled out because
             * Color::hex() made 600 so light that Filament drew primary buttons pale
             * blue with dark text; with this palette they are solid blue with white.
             */
            ->colors([
                'primary' => [
                    50 => '#f0f5fb',
                    100 => '#dde8f6',
                    200 => '#c0d4ee',
                    300 => '#93b5e1',
                    400 => '#6390d0',
                    500 => '#3f70bf',
                    600 => '#2b58a4',
                    700 => '#244787',
                    800 => '#1e3a5f',
                    900 => '#1a3050',
                    950 => '#101d33',
                ],
            ])
            ->brandLogo(asset('fynnedge-icon.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
            /*
             * Plus Jakarta Sans is self-hosted through Vite (vite.config.js) because
             * the CSP only allows fonts from this origin; the head hook below prints
             * its @font-face rules. LocalFontProvider stops Filament adding a link to
             * an external font CDN.
             */
            ->font('Plus Jakarta Sans', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => self::adminFonts())
            /*
             * Lists and forms use the whole width of the screen, so wide tables fit
             * without a sideways scroll on ordinary laptop and desktop screens.
             */
            ->maxContentWidth(Width::Full)
            ->sidebarWidth('18rem')
            /*
             * The sidebar lists main modules only (App\Filament\Clusters\AdminModules);
             * each module's screens are tabs across the top of the page. These three
             * headings band the modules and are always open (no collapse arrows) —
             * URLs are unchanged by any of it.
             */
            ->navigationGroups([
                NavigationGroup::make('Operations')->collapsible(false),
                NavigationGroup::make('Website & Marketing')->collapsible(false),
                NavigationGroup::make('Settings & Support')->collapsible(false),
            ])
            ->sidebarCollapsibleOnDesktop()
            /*
             * A heading collapsed in a browser before the headings were fixed open
             * stays hidden (Filament remembers it in localStorage) with no arrow to
             * reopen it; this re-opens such headings. See the view for details.
             */
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_END, fn (): View => view('filament.admin.sidebar-open-groups'))
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): View => view('filament.admin.topbar-brand'))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): View => view('filament.admin.topbar-actions'))
            /*
             * Built-in help on every screen (App\Support\Admin\AdminGuide): a one-line
             * summary under the title, a "Before you start" checklist while something
             * the screen depends on is missing, and an "About this screen" panel below
             * the content. Screens without a guide entry render nothing extra.
             */
            ->renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_AFTER, fn (array $scopes): View|string => AdminHelpPanels::summary($scopes))
            ->renderHook(PanelsRenderHook::PAGE_HEADER_WIDGETS_BEFORE, fn (array $scopes): View|string => AdminHelpPanels::prerequisites($scopes))
            ->renderHook(PanelsRenderHook::PAGE_FOOTER_WIDGETS_AFTER, fn (array $scopes): View|string => AdminHelpPanels::guide($scopes))
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            /*
             * FilamentInfoWidget (the "filament vX.Y" card linking to Filament's
             * docs and GitHub) is deliberately NOT registered: this is a client-
             * facing admin panel, and the framework's own branding and version
             * number are neither useful to the staff using it nor something to
             * advertise publicly. Filament only renders the widgets listed here,
             * so removing it from the array is the whole fix.
             */
            /*
             * The dashboard's own widgets are discovered from app/Filament/Widgets
             * (WelcomeWidget replaced Filament's generic AccountWidget card; signing
             * out stays in the user menu).
             */
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SecurityHeaders::class,
            ])
            ->plugins([
                // Roles join the Administration module via config/filament-shield.php's
                // shield_resource.cluster; null keeps them a plain tab rather than a
                // "Filament Shield" dropdown inside it.
                FilamentShieldPlugin::make()
                    ->navigationGroup(null)
                    ->navigationSort(20),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * The admin typeface's @font-face rules and preload links. A missing font
     * build must never take the admin down, so it falls back to system fonts.
     */
    private static function adminFonts(): HtmlString
    {
        try {
            return Vite::fonts('plus-jakarta-sans');
        } catch (ViteException) {
            return new HtmlString('');
        }
    }
}
