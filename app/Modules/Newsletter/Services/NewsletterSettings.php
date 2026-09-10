<?php

namespace App\Modules\Newsletter\Services;

use App\Models\Setting;

/**
 * Newsletter configuration, read from the SAME key/value Setting store the rest
 * of the site uses — no second settings framework, no config file to redeploy.
 *
 * Sender identity falls back to the app's configured mail.from, so an install
 * that never opens the newsletter settings page still sends valid mail rather
 * than mail from an empty address. Credentials are never stored here: the
 * transport stays entirely in Laravel's mail config/.env.
 */
class NewsletterSettings
{
    public static function enabled(): bool
    {
        return (bool) Setting::get('newsletter_enabled', true);
    }

    public static function doubleOptInEnabled(): bool
    {
        return (bool) Setting::get('double_opt_in_enabled', true);
    }

    public static function welcomeEmailEnabled(): bool
    {
        return (bool) Setting::get('welcome_email_enabled', true);
    }

    public static function senderName(): string
    {
        return self::string('newsletter_sender_name')
            ?? (string) (config('mail.from.name') ?: Setting::get('site_name', 'FynnEdge'));
    }

    public static function senderEmail(): string
    {
        return self::string('newsletter_sender_email')
            ?? (string) config('mail.from.address');
    }

    public static function replyTo(): ?string
    {
        return self::string('newsletter_reply_to') ?? self::string('contact_email');
    }

    /**
     * Shown in every email footer. Postal identification is a CAN-SPAM/most-ESP
     * requirement, so it reuses the business address an admin already entered
     * under Settings rather than asking for it twice.
     */
    public static function postalAddress(): ?string
    {
        $parts = array_filter([
            Setting::get('footer_legal_name'),
            Setting::get('business_street_address'),
            Setting::get('business_locality'),
            Setting::get('business_region'),
            Setting::get('business_postal_code'),
        ], fn (mixed $value): bool => filled($value));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function string(string $key): ?string
    {
        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
