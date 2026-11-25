<?php

namespace App\Modules\FynnOn\Services;

use App\Modules\Leads\Enums\LeadSource;

/**
 * Where and how leads are pushed to FynnOn LMS. Credentials live only in the
 * server environment (config/services.php → fynnon); the admin shows whether
 * they are set, never their values.
 */
final class FynnOnSettings
{
    public static function isConfigured(): bool
    {
        return filled(config('services.fynnon.url')) && filled(config('services.fynnon.token'));
    }

    public static function url(): ?string
    {
        return config('services.fynnon.url');
    }

    public static function token(): ?string
    {
        return config('services.fynnon.token');
    }

    public static function signingSecret(): ?string
    {
        return config('services.fynnon.signing_secret') ?: null;
    }

    public static function timeout(): int
    {
        return max(1, (int) config('services.fynnon.timeout', 15));
    }

    public static function alertEmail(): ?string
    {
        $email = config('services.fynnon.alert_email');

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @return array<int, LeadSource>
     */
    public static function enabledSources(): array
    {
        return collect(explode(',', (string) config('services.fynnon.sources', '')))
            ->map(fn (string $value): ?LeadSource => LeadSource::tryFrom(trim($value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function sends(LeadSource $source): bool
    {
        return self::isConfigured() && in_array($source, self::enabledSources(), true);
    }

    /**
     * The LMS host only (no path or query), for display.
     */
    public static function host(): ?string
    {
        $host = parse_url((string) self::url(), PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }
}
