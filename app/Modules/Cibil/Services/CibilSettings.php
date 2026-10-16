<?php

namespace App\Modules\Cibil\Services;

use App\Models\Setting;
use App\Modules\Cibil\Providers\ExperianCibilScoreProvider;
use App\Modules\Cibil\Providers\SurepassCibilScoreProvider;
use App\Modules\CreditBureau\Services\SurepassClient;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
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
 *  - The public "Free CIBIL Score" page: off (a labelled demo score) or on,
 *    answered by the SOURCE named in CIBIL_PUBLIC_SCORE_SOURCE — "cibil" (the
 *    real CIBIL bureau) or "experian" (the stand-in used before CIBIL was
 *    integrated, which tells the visitor the score is Experian's).
 */
final class CibilSettings
{
    public const ENABLED = 'cibil_enabled';

    public const PUBLIC_SCORE_ENABLED = 'cibil_public_score_enabled';

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

    /**
     * Where real scores for the public CIBIL page come from, by config value.
     *
     * @var array<string, array{label: string, provider: class-string<CreditScoreProvider>}>
     */
    public const SOURCES = [
        self::SOURCE_CIBIL => ['label' => 'CIBIL', 'provider' => SurepassCibilScoreProvider::class],
        'experian' => ['label' => 'Experian', 'provider' => ExperianCibilScoreProvider::class],
    ];

    /**
     * Off by default: a new install must not make paid calls until an admin has
     * reviewed the reuse and consent settings and switched it on.
     */
    public static function enabled(): bool
    {
        return (bool) Setting::get(self::ENABLED, false);
    }

    /** Off by default: every new check on the public page is a paid call triggered by a website visitor. */
    public static function publicScoreEnabled(): bool
    {
        return (bool) Setting::get(self::PUBLIC_SCORE_ENABLED, false);
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

    /** The configured public-page source key, or null when it names no known source. */
    public static function source(): ?string
    {
        $source = config('services.cibil.public_score_source');

        return is_string($source) && array_key_exists($source, self::SOURCES) ? $source : null;
    }

    /** The bureau whose score the public CIBIL page shows — "CIBIL", or "Experian" while it stands in. */
    public static function sourceLabel(): ?string
    {
        $source = self::source();

        return $source === null ? null : self::SOURCES[$source]['label'];
    }

    /** Whether the public CIBIL page is answered by another bureau standing in for CIBIL. */
    public static function isStandIn(): bool
    {
        $source = self::source();

        return $source !== null && $source !== self::SOURCE_CIBIL;
    }

    /** Whether the public page's source can be called from this server (its token is set). */
    public static function publicSourceConfigured(): bool
    {
        return match (self::source()) {
            self::SOURCE_CIBIL => self::isConfigured(),
            'experian' => ExperianSettings::isConfigured(),
            default => false,
        };
    }

    public static function publicScoreLive(): bool
    {
        return self::publicScoreEnabled() && self::publicSourceConfigured();
    }

    /** The provider answering the public page right now, or null when it shows demo scores. */
    public static function liveProvider(): ?CreditScoreProvider
    {
        $source = self::source();

        return self::publicScoreLive() && $source !== null ? app(self::SOURCES[$source]['provider']) : null;
    }

    /** How long a visitor's stored score is shown again instead of paying for a new one — the source bureau's own period. */
    public static function publicScoreReuseDays(): int
    {
        return match (self::source()) {
            'experian' => ExperianSettings::scoreReuseDays(),
            default => self::scoreReuseDays(),
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
