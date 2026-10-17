<?php

namespace App\Modules\Cibil\Services;

use App\Models\Setting;
use App\Modules\Cibil\Providers\ExperianCibilScoreProvider;
use App\Modules\Cibil\Providers\SurepassCibilScoreProvider;
use App\Modules\CreditBureau\Services\SurepassClient;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\Experian\Services\ExperianSettings;

/**
 * CIBIL configuration, edited at Admin → CIBIL → CIBIL Settings and stored in
 * the SAME key/value Setting table the rest of the site uses — independent of
 * CRIF's, Equifax's and Experian's settings.
 *
 * Two concerns live here:
 *
 *  - The CIBIL bureau integration (Surepass credit-report-cibil): staff and
 *    API requests, reuse periods, consent validity, retention, alerts. Its
 *    token is SUREPASS_CIBIL_TOKEN only — deliberately NO fallback to the
 *    shared or another bureau's token — and no screen shows it.
 *  - The public "Get CIBIL Score" page, decided here and nowhere else by two
 *    independent admin switches (user decision, 2026-10-17):
 *      1. "Real CIBIL score enabled" (PUBLIC_SCORE_ENABLED): off = a labelled
 *         demo score and no bureau call at all, whatever switch 2 says.
 *      2. "Fetch Experian instead of CIBIL" (PUBLIC_SCORE_USE_EXPERIAN): off =
 *         the real CIBIL score (Surepass CIBIL), on = the real Experian score
 *         (Surepass Experian), shown and stored as Experian's. Neither source
 *         ever falls back to the other.
 *    A chosen source whose token is missing shows the labelled demo score, as
 *    before; the settings page says so.
 */
final class CibilSettings
{
    public const ENABLED = 'cibil_enabled';

    /** Switch 1: the public page shows real scores (off = labelled demo, no bureau call). */
    public const PUBLIC_SCORE_ENABLED = 'cibil_public_score_enabled';

    /** Switch 2: those real scores come from Experian instead of CIBIL. */
    public const PUBLIC_SCORE_USE_EXPERIAN = 'cibil_public_score_use_experian';

    public const SCORE_REUSE_DAYS = 'cibil_score_reuse_days';

    public const REPORT_REUSE_DAYS = 'cibil_report_reuse_days';

    public const CONSENT_VALIDITY_DAYS = 'cibil_consent_validity_days';

    public const RETENTION_DAYS = 'cibil_retention_days';

    public const ALERT_EMAIL = 'cibil_alert_email';

    public const DEFAULT_SCORE_REUSE_DAYS = 30;

    public const DEFAULT_REPORT_REUSE_DAYS = 30;

    public const DEFAULT_CONSENT_VALIDITY_DAYS = 30;

    public const DEFAULT_RETENTION_DAYS = 365;

    /** The real CIBIL bureau, as a public-page source. */
    public const SOURCE_CIBIL = 'cibil';

    /** The visitor's Experian score, labelled as Experian's, as a public-page source. */
    public const SOURCE_EXPERIAN = 'experian';

    /**
     * Where real scores for the public CIBIL page come from, by source key.
     *
     * @var array<string, array{label: string, provider: class-string<CreditScoreProvider>}>
     */
    public const SOURCES = [
        self::SOURCE_CIBIL => ['label' => 'CIBIL', 'provider' => SurepassCibilScoreProvider::class],
        self::SOURCE_EXPERIAN => ['label' => 'Experian', 'provider' => ExperianCibilScoreProvider::class],
    ];

    /**
     * Off by default: a new install must not make paid calls until an admin has
     * reviewed the reuse and consent settings and switched it on.
     */
    public static function enabled(): bool
    {
        return (bool) Setting::get(self::ENABLED, false);
    }

    /** Switch 1. Off by default: every new check on the public page is a paid call triggered by a website visitor. */
    public static function publicScoreEnabled(): bool
    {
        return (bool) Setting::get(self::PUBLIC_SCORE_ENABLED, false);
    }

    /** Switch 2. Off by default: real scores come from CIBIL. */
    public static function publicScoreUsesExperian(): bool
    {
        return (bool) Setting::get(self::PUBLIC_SCORE_USE_EXPERIAN, false);
    }

    /** Whether the CIBIL token (SUREPASS_CIBIL_TOKEN) and base URL are set on this server. */
    public static function isConfigured(): bool
    {
        return self::surepassClient()->isConfigured();
    }

    public static function isAvailable(): bool
    {
        return self::enabled() && self::isConfigured();
    }

    /**
     * The Surepass wire CIBIL calls go through (bound for SurepassCibilService
     * in AppServiceProvider): CIBIL's own token and base URL, never the shared
     * or another bureau's credentials.
     */
    public static function surepassClient(): SurepassClient
    {
        return new SurepassClient(
            config('services.surepass.cibil_token'),
            config('services.surepass.cibil_base_url'),
            useSharedCredentials: false,
        );
    }

    /** Which environment variable the CIBIL token is read from — the name only; null when it is not set. */
    public static function tokenSource(): ?string
    {
        return filled(config('services.surepass.cibil_token')) ? 'SUREPASS_CIBIL_TOKEN' : null;
    }

    /** The public page's source key, from switch 2 alone — never chosen by which tokens happen to be set. */
    public static function source(): string
    {
        return self::publicScoreUsesExperian() ? self::SOURCE_EXPERIAN : self::SOURCE_CIBIL;
    }

    /** The bureau whose score the public CIBIL page shows — "CIBIL", or "Experian" while switch 2 is on. */
    public static function sourceLabel(): string
    {
        return self::SOURCES[self::source()]['label'];
    }

    /** The bureau that answers the public CIBIL page's real checks — what its stored scores are kept apart by. */
    public static function sourceBureau(): BureauName
    {
        return self::isStandIn() ? BureauName::Experian : BureauName::Cibil;
    }

    /** Whether the public CIBIL page is answered by Experian instead of CIBIL. */
    public static function isStandIn(): bool
    {
        return self::source() !== self::SOURCE_CIBIL;
    }

    /** Whether the chosen source can be called from this server (its token is set). */
    public static function publicSourceConfigured(): bool
    {
        return match (self::source()) {
            self::SOURCE_CIBIL => self::isConfigured(),
            self::SOURCE_EXPERIAN => ExperianSettings::isConfigured(),
        };
    }

    public static function publicScoreLive(): bool
    {
        return self::publicScoreEnabled() && self::publicSourceConfigured();
    }

    /** The provider answering the public page right now, or null when it shows demo scores. */
    public static function liveProvider(): ?CreditScoreProvider
    {
        return self::publicScoreLive() ? app(self::SOURCES[self::source()]['provider']) : null;
    }

    /** How long a visitor's stored score is shown again instead of paying for a new one — the source bureau's own period. */
    public static function publicScoreReuseDays(): int
    {
        return match (self::source()) {
            self::SOURCE_EXPERIAN => ExperianSettings::scoreReuseDays(),
            self::SOURCE_CIBIL => self::scoreReuseDays(),
        };
    }

    /** How long a score result is shown instead of paying for a new score. */
    public static function scoreReuseDays(): int
    {
        return self::days(self::SCORE_REUSE_DAYS, self::DEFAULT_SCORE_REUSE_DAYS);
    }

    /** How long a full report (or stored PDF) is shown instead of paying for a new one. */
    public static function reportReuseDays(): int
    {
        return self::days(self::REPORT_REUSE_DAYS, self::DEFAULT_REPORT_REUSE_DAYS);
    }

    public static function consentValidityDays(): int
    {
        return self::days(self::CONSENT_VALIDITY_DAYS, self::DEFAULT_CONSENT_VALIDITY_DAYS);
    }

    /** After this many days a result's contents are purged; never shorter than either reuse period. */
    public static function retentionDays(): int
    {
        return max(self::days(self::RETENTION_DAYS, self::DEFAULT_RETENTION_DAYS), self::scoreReuseDays(), self::reportReuseDays());
    }

    public static function alertEmail(): ?string
    {
        $email = Setting::get(self::ALERT_EMAIL);

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function days(string $key, int $default): int
    {
        $value = Setting::get($key, $default);

        return is_numeric($value) && (int) $value >= 1 ? (int) $value : $default;
    }
}
