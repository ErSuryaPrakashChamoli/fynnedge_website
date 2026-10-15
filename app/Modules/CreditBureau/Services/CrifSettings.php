<?php

namespace App\Modules\CreditBureau\Services;

use App\Models\Setting;

/**
 * CRIF report business configuration, edited at Admin → Credit Bureau → CRIF
 * Settings and stored in the SAME key/value Setting table the rest of the site
 * uses. The Surepass token is deliberately NOT here: it stays in the server's
 * environment (config/services.php), so no admin screen can show or change it.
 */
final class CrifSettings
{
    public const ENABLED = 'crif_enabled';

    public const PUBLIC_SCORE_ENABLED = 'crif_public_score_enabled';

    public const REUSE_DAYS = 'crif_reuse_days';

    public const CONSENT_VALIDITY_DAYS = 'crif_consent_validity_days';

    public const RETENTION_DAYS = 'crif_retention_days';

    public const ALERT_EMAIL = 'crif_alert_email';

    public const DEFAULT_REUSE_DAYS = 30;

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
     * Whether the public "Free CRIF Score" page shows real scores. Off by
     * default and separate from enabled(): every new check there is a paid
     * Surepass call triggered by a website visitor, not by an employee.
     */
    public static function publicScoreEnabled(): bool
    {
        return (bool) Setting::get(self::PUBLIC_SCORE_ENABLED, false);
    }

    public static function publicScoreLive(): bool
    {
        return self::publicScoreEnabled() && self::isConfigured();
    }

    public static function isConfigured(): bool
    {
        return filled(config('services.surepass.token')) && filled(config('services.surepass.base_url'));
    }

    public static function isAvailable(): bool
    {
        return self::enabled() && self::isConfigured();
    }

    /** How long a successful report is shown instead of paying for a new one. */
    public static function reuseDays(): int
    {
        return self::days(self::REUSE_DAYS, self::DEFAULT_REUSE_DAYS);
    }

    /** How old a recorded consent may be and still authorise a pull. */
    public static function consentValidityDays(): int
    {
        return self::days(self::CONSENT_VALIDITY_DAYS, self::DEFAULT_CONSENT_VALIDITY_DAYS);
    }

    /** After this many days a report's contents are purged and it becomes Expired. */
    public static function retentionDays(): int
    {
        return max(self::days(self::RETENTION_DAYS, self::DEFAULT_RETENTION_DAYS), self::reuseDays());
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
