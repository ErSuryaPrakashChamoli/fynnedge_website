<?php

namespace App\Providers;

use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use App\Models\NavigationLink;
use App\Models\Setting;
use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Support\Analytics\TrackingScripts;
use App\Support\Theme\SiteThemeStyles;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            CreditBureauProvider::class,
            fn () => $this->app->make(config('services.credit_bureau.provider')),
        );

        $this->app->bind(
            CreditScoreProvider::class,
            fn () => $this->app->make(config('services.credit_score.provider')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gold Loan, Two Wheeler, Term, Tractor and Mudra loans are excluded here to keep
        // this list short — they stay listed everywhere else (home, /loans, header mega-menu).
        View::composer(
            ['components.site.header', 'components.site.footer'],
            fn ($view) => $view->with(
                'loanProducts',
                LoanProduct::query()->published()->orderedForDisplay()
                    ->whereNotIn('category', [
                        LoanCategory::GoldLoan,
                        LoanCategory::TwoWheelerLoan,
                        LoanCategory::TermLoan,
                        LoanCategory::TractorLoan,
                        LoanCategory::MudraLoan,
                    ])
                    ->get(),
            ),
        );

        // Shared across header, footer and the base layout (browser tab title) so the
        // name/logo/tagline/favicon an admin sets on the Settings page stays in sync
        // everywhere it appears, instead of three independently hard-coded copies.
        View::composer(
            ['components.site.header', 'components.site.footer', 'components.layouts.app'],
            fn ($view) => $view->with('siteBranding', [
                'name' => Setting::get('site_name', 'FynnEdge'),
                'tagline' => Setting::get('site_tagline', 'Simplifying Loan, Amplifying Trust'),
                'logoUrl' => static::settingFileUrl('site_logo') ?? asset('fynnedge-icon.png'),
                'faviconUrl' => static::settingFileUrl('site_favicon') ?? asset('favicon.ico'),
                'faviconIsCustom' => (bool) Setting::get('site_favicon'),
                'defaultOgImageUrl' => static::settingFileUrl('seo_default_og_image'),
            ]),
        );

        View::composer(
            'components.site.footer',
            fn ($view) => $view->with('socialLinks', [
                'Instagram' => Setting::get('social_instagram'),
                'Facebook' => Setting::get('social_facebook'),
                'WhatsApp' => Setting::get('social_whatsapp'),
                'LinkedIn' => Setting::get('social_linkedin'),
                'X' => Setting::get('social_x'),
            ]),
        );

        View::composer(
            'components.site.footer',
            fn ($view) => $view->with('contactDetails', [
                'address' => Setting::get('contact_address'),
                'phone' => Setting::get('contact_phone'),
                'email' => Setting::get('contact_email'),
            ]),
        );

        View::composer(
            'components.site.footer',
            fn ($view) => $view->with('footerLegal', [
                'name' => Setting::get('footer_legal_name', 'FynnEdge Advisory (OPC) Pvt Ltd'),
                'disclaimer' => Setting::get('footer_disclaimer', 'Loan approval is subject to lender policies, documentation and underwriting. Eligibility results shown on this site are indicative, not a guarantee of approval.'),
            ]),
        );

        // Admin-managed links appended alongside the footer's existing hardcoded
        // Company/Legal columns — never a replacement for them. Empty by default,
        // so an unconfigured install renders exactly as before.
        View::composer(
            'components.site.footer',
            fn ($view) => $view->with(
                'navigationLinks',
                NavigationLink::query()->active()->forLocation('footer')->whereNull('parent_id')->orderBy('sort_order')->get(),
            ),
        );

        // Analytics, verification and custom tracking tags, from the SEO & Analytics
        // settings page. Composed here — onto the one layout every public page uses —
        // rather than in each view, so a tag is injected exactly once per page and no
        // Blade file ever holds a measurement/container ID.
        View::composer(
            'components.layouts.app',
            fn ($view) => $view->with([
                'trackingHead' => TrackingScripts::head(),
                'trackingBodyStart' => TrackingScripts::bodyStart(),
                'trackingBodyEnd' => TrackingScripts::bodyEnd(),
            ]),
        );

        // Renders once, in the <head>, as a scoped <style> block overriding the
        // header/footer/main-content CSS variables an admin set on the Settings
        // page's Appearance section. Empty string (nothing rendered) when unset.
        View::composer(
            'components.layouts.app',
            fn ($view) => $view->with('siteThemeStyles', SiteThemeStyles::render()),
        );

        // Generous enough for a real applicant working through a multi-step journey with
        // back navigation, tight enough to blunt scripted spam against the eligibility
        // and application flow. Uncapped under the test runner so a large suite hitting
        // these routes from the same client IP within one process doesn't self-throttle.
        RateLimiter::for('public-forms', fn (Request $request) => app()->runningUnitTests()
            ? Limit::none()
            : Limit::perMinute(60)->by($request->ip()));

        // Tighter than the contact form: a newsletter signup triggers an email to an
        // address the submitter chose, so an unthrottled endpoint is a mail-bombing
        // tool aimed at third parties, not just a spam problem for us.
        RateLimiter::for('newsletter', fn (Request $request) => app()->runningUnitTests()
            ? Limit::none()
            : [Limit::perMinute(5)->by($request->ip()), Limit::perDay(20)->by($request->ip())]);

        RateLimiter::for('contact-form', fn (Request $request) => app()->runningUnitTests()
            ? Limit::none()
            : Limit::perMinute(5)->by($request->ip()));
    }

    private static function settingFileUrl(string $key): ?string
    {
        $path = Setting::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
