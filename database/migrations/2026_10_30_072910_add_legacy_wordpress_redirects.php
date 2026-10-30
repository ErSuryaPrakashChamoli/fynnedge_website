<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Old WordPress URLs that Google still crawls (Search Console coverage
     * report, October 2026) and that have a genuinely equivalent page on this
     * site. Each one currently ends in a 404. Only URLs whose old slug names
     * the same subject as the new page are listed; the rest of that report's
     * legacy URLs stay 404 until someone who knows the old content picks a
     * destination in Admin → Redirects.
     *
     * Shipped as a migration because production only runs `migrate`. A source
     * that already has a row, active or not, is left alone: that row is an
     * admin's decision and wins.
     *
     * @var array<string, string> source path => destination
     */
    private const REDIRECTS = [
        '/credit-card' => '/loans/credit-card',
        '/fixed-versus-floating-interest-rates-on-personal-loans-which-one-is-superior' => '/resources/fixed-vs-floating-interest-rates',
    ];

    public function up(): void
    {
        foreach (self::REDIRECTS as $source => $destination) {
            Redirect::query()->firstOrCreate(
                ['source_path' => $source],
                ['destination' => $destination, 'status_code' => 301, 'is_active' => true],
            );
        }
    }

    /**
     * Removes only rows still exactly as this migration created them, so an
     * admin's edit to one of them survives a rollback.
     */
    public function down(): void
    {
        foreach (self::REDIRECTS as $source => $destination) {
            Redirect::query()
                ->where('source_path', $source)
                ->where('destination', $destination)
                ->delete();
        }

        Redirect::forgetCache();
    }
};
