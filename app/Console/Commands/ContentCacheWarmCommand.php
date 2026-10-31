<?php

namespace App\Console\Commands;

use App\Models\PageSeo;
use App\Models\Redirect;
use App\Models\Setting;
use App\Support\ContentCache\ContentCache;
use App\Support\Loans\LoanMegaMenu;
use App\Support\Localization\SiteLocale;
use App\Support\Rates\RateBook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * Builds the shared cache entries every page reads — settings, rates,
 * redirects, SEO overrides and the mega menu in each language — so the first
 * visitors after a deploy or a Redis restart don't pay for them.
 *
 * It calls the same readers the pages use, in this process: no HTTP request,
 * so no session, form, OTP, bureau call or database write is ever triggered,
 * and only published content can be built (the readers filter it).
 */
class ContentCacheWarmCommand extends Command
{
    protected $signature = 'content-cache:warm';

    protected $description = 'Build the shared public content cache entries (settings, rates, redirects, SEO, menus)';

    public function handle(): int
    {
        if (! ContentCache::enabled()) {
            $this->components->warn('The content cache is disabled (CONTENT_CACHE_ENABLED=false); nothing to warm.');

            return self::SUCCESS;
        }

        $this->components->task('Settings', fn () => Setting::get('site_name') !== false);
        $this->components->task('Rates', fn () => RateBook::instance()->live() !== null);
        $this->components->task('Redirects', fn () => is_array(Redirect::activeMap()));
        $this->components->task('Per-URL SEO overrides', fn () => is_array(PageSeo::activeMap()));

        $locale = App::getLocale();

        foreach (array_keys(SiteLocale::SUPPORTED) as $supported) {
            App::setLocale($supported);
            // The rendered panel is keyed by site address; from the console
            // that is APP_URL, so it matches visitors when APP_URL is right.
            $this->components->task("Loan mega menu ({$supported})", fn () => is_array(LoanMegaMenu::categories()) && LoanMegaMenu::panelHtml() !== '');
        }

        App::setLocale($locale);

        return self::SUCCESS;
    }
}
