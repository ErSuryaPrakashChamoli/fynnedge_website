<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

/**
 * /about-us → /about used to be a Route::permanentRedirect() in routes/web.php.
 * A route-level redirect is invisible to HandleRedirects, so "/about-us/" took
 * two hops: the middleware's slash normalisation (/about-us/ → /about-us), then
 * the route (/about-us → /about). Search Console listed it under "Page with
 * redirect" (November 2026 validation export). As a redirects row it is matched
 * whatever the slashes, so both forms answer one 301 straight to /about.
 *
 * A source that already has a row, active or not, is left alone: that row is
 * an admin's decision and wins (same rule as the October 2026 migrations).
 */
return new class extends Migration
{
    private const SOURCE = '/about-us';

    private const DESTINATION = '/about';

    public function up(): void
    {
        Redirect::query()->firstOrCreate(
            ['source_path' => self::SOURCE],
            [
                'destination' => self::DESTINATION,
                'status_code' => Redirect::PERMANENT,
                'is_active' => true,
                'notes' => 'Old WordPress About Us page. Was a route-level redirect in routes/web.php, which made /about-us/ a two-hop chain (Search Console "Page with redirect", Nov 2026).',
            ],
        );
    }

    /**
     * Removes the row only if it is still exactly as this migration created
     * it, so an admin's edit survives a rollback.
     */
    public function down(): void
    {
        Redirect::query()
            ->where('source_path', self::SOURCE)
            ->where('destination', self::DESTINATION)
            ->where('status_code', Redirect::PERMANENT)
            ->delete();

        Redirect::forgetCache();
    }
};
