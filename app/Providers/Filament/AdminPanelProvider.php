<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SecurityHeaders;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
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
            ->colors([
                'primary' => Color::hex('#1E3A5F'),
            ])
            ->brandLogo(asset('fynnedge-icon.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
            ->navigationGroups([
                NavigationGroup::make('Catalog'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('Access Control'),
            ])
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
