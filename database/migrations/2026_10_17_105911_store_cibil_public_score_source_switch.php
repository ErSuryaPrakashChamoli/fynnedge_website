<?php

use App\Models\Setting;
use App\Modules\Cibil\Services\CibilSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * The Get CIBIL Score page's bureau is now an admin switch, CIBIL Settings →
 * "Fetch Experian instead of CIBIL" (CibilSettings::PUBLIC_SCORE_USE_EXPERIAN),
 * replacing the server's CIBIL_PUBLIC_SCORE_SOURCE and the automatic choice
 * ("cibil" once SUREPASS_CIBIL_TOKEN was set, the "experian" stand-in until
 * then). Where the page already shows real scores, this stores the switch as
 * whatever that old rule chose on this server, so the page keeps the same
 * bureau after the deploy until an admin changes it. Where it shows demo
 * scores nothing is stored: the switch starts off (CIBIL) and the admin picks
 * the bureau when turning real scores on. A switch already saved is left alone.
 *
 * The old value is read from config (still there if a pre-deploy config cache
 * is in place) or the environment (when it is not).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! CibilSettings::publicScoreEnabled()
            || Setting::query()->where('key', CibilSettings::PUBLIC_SCORE_USE_EXPERIAN)->exists()) {
            return;
        }

        $forced = config('services.cibil.public_score_source') ?? env('CIBIL_PUBLIC_SCORE_SOURCE');

        $usedExperian = match ($forced) {
            CibilSettings::SOURCE_EXPERIAN => true,
            CibilSettings::SOURCE_CIBIL => false,
            default => ! CibilSettings::isConfigured(),
        };

        Setting::set(CibilSettings::PUBLIC_SCORE_USE_EXPERIAN, $usedExperian);
    }

    public function down(): void
    {
        Setting::query()->where('key', CibilSettings::PUBLIC_SCORE_USE_EXPERIAN)->delete();
        cache()->forget('setting:'.CibilSettings::PUBLIC_SCORE_USE_EXPERIAN);
    }
};
