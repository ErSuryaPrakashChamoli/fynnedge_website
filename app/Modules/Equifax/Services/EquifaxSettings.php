<?php

namespace App\Modules\Equifax\Services;

use App\Models\Setting;
use App\Modules\CreditBureau\Services\SurepassClient;

/**
 * Equifax business configuration, edited at Admin → Equifax → Equifax Settings
 * and stored in the SAME key/value Setting table the rest of the site uses.
 * Independent of CRIF's settings: switching one bureau off never touches the
 * other. The Surepass token is deliberately NOT here — it stays in the server
 * environment (config/services.php): SUREPASS_EQUIFAX_TOKEN when set, else the
 * SUREPASS_BEARER_TOKEN shared with CRIF — and no screen shows it.
 */
final class EquifaxSettings
{
    public const ENABLED = 'equifax_enabled';

    public const PUBLIC_SCORE_ENABLED = 'equifax_public_score_enabled';

    public const SCORE_REUSE_DAYS = 'equifax_score_reuse_days';

    public const REPORT_REUSE_DAYS = 'equifax_report_reuse_days';

    public const CONSENT_VALIDITY_DAYS = 'equifax_consent_validity_days';

    public const RETENTION_DAYS = 'equifax_retention_days';

    public const ALERT_EMAIL = 'equifax_alert_email';

    public const DEFAULT_SCORE_REUSE_DAYS = 30;

    public const DEFAULT_REPORT_REUSE_DAYS = 30;

    public const DEFAULT_CONSENT_VALIDITY_DAYS = 30;

    public const DEFAULT_RETENTION_DAYS = 365;

    /**
     * Off by default: a new install must not make paid calls until an admin has
     * reviewed the reuse and consent settings and switched it on.
     */
    public static function enabled(): bool
    {
        return (bool) Setting::get(self::ENABLED, false);
    }

    /**
     * Whether the public "Free Equifax Score" page shows real scores. Off by
     * default and separate from enabled(): every new check there is a paid
     * Surepass call triggered by a website visitor, not by an employee.
     */
    public static function publicScoreEnabled(): bool
    {
        return (bool) Setting::get(self::PUBLIC_SCORE_ENABLED, false);
    }

    public static function isConfigured(): bool
    {
        return self::surepassClient()->isConfigured();
    }

    /**
     * The Surepass wire Equifax calls go through (bound for
     * SurepassEquifaxService in AppServiceProvider): the Equifax-only token and
     * base URL when the server sets them, otherwise the shared ones.
     */
    public static function surepassClient(): SurepassClient
    {
        return new SurepassClient(
            config('services.surepass.equifax_token'),
            config('services.surepass.equifax_base_url'),
        );
    }

    /**
     * Which environment variable the Equifax token is read from — the name
     * only, for diagnostics; null when neither is set.
     */
    public static function tokenSource(): ?string
    {
        return match (true) {
            filled(config('services.surepass.equifax_token')) => 'SUREPASS_EQUIFAX_TOKEN',
            filled(config('services.surepass.token')) => 'SUREPASS_BEARER_TOKEN, shared with CRIF',
            default => null,
        };
    }

    public static function isAvailable(): bool
    {
        return self::enabled() && self::isConfigured();
    }

    public static function publicScoreLive(): bool
    {
        return self::publicScoreEnabled() && self::isConfigured();
    }

    /** How long a score result is shown instead of paying for a new score. */
    public static function scoreReuseDays(): int
    {
        return self::days(self::SCORE_REUSE_DAYS, self::DEFAULT_SCORE_REUSE_DAYS);
    }

    /** How long a full report is shown instead of paying for a new report. */
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
