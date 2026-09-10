<?php

namespace App\Support\Privacy;

use App\Models\Setting;
use Illuminate\Support\Facades\Cookie;

/**
 * The visitor's cookie-consent state, and the policy an admin set for it.
 *
 * Consent is stored in one first-party cookie holding the granted categories
 * ("analytics", "marketing"), written by the banner in
 * resources/views/components/site/cookie-consent.blade.php. It is read
 * SERVER-side here so a script that has no consent is never rendered into the
 * page at all — a client-side "block after the fact" would still have loaded
 * the third-party JS, which is exactly what consent is supposed to prevent.
 *
 * Nothing is gated unless an admin turns the banner on AND marks that category
 * as requiring consent, so a site that has made its own legal assessment (or
 * operates where implied consent is enough) keeps today's behaviour. The
 * banner writes its cookie and reloads, so the next request renders the tags.
 */
class CookieConsent
{
    public const COOKIE = 'fynnedge_cookie_consent';

    public const ANALYTICS = 'analytics';

    public const MARKETING = 'marketing';

    /**
     * A year is long enough not to nag, short enough that consent is
     * periodically re-confirmed.
     */
    public const LIFETIME_DAYS = 365;

    public static function bannerEnabled(): bool
    {
        return (bool) Setting::get('cookie_consent_enabled', false);
    }

    public static function analyticsRequired(): bool
    {
        return self::bannerEnabled() && (bool) Setting::get('analytics_consent_required', false);
    }

    public static function marketingRequired(): bool
    {
        return self::bannerEnabled() && (bool) Setting::get('marketing_consent_required', false);
    }

    public static function allowsAnalytics(): bool
    {
        return ! self::analyticsRequired() || self::granted(self::ANALYTICS);
    }

    public static function allowsMarketing(): bool
    {
        return ! self::marketingRequired() || self::granted(self::MARKETING);
    }

    /**
     * Whether the banner should be shown to this visitor: only when it is
     * switched on and they have not answered it yet.
     */
    public static function shouldPrompt(): bool
    {
        return self::bannerEnabled() && self::rawCookie() === null;
    }

    public static function granted(string $category): bool
    {
        return in_array($category, self::grantedCategories(), strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function grantedCategories(): array
    {
        $raw = self::rawCookie();

        if ($raw === null) {
            return [];
        }

        return array_values(array_intersect(
            array_map(trim(...), explode(',', $raw)),
            [self::ANALYTICS, self::MARKETING],
        ));
    }

    /**
     * @return array<string, string|null>
     */
    public static function policyLinks(): array
    {
        return array_filter([
            'Cookie policy' => Setting::get('cookie_policy_url'),
            'Privacy policy' => Setting::get('privacy_policy_url'),
            'Terms' => Setting::get('terms_url'),
        ], fn (mixed $url): bool => filled($url));
    }

    /**
     * Read straight off the request rather than the Cookie facade's queue, and
     * unencrypted: the banner writes it from JavaScript (that is the only place
     * the visitor's answer exists), so Laravel's cookie encryption would reject
     * it. It holds no personal data — just which categories were accepted.
     */
    private static function rawCookie(): ?string
    {
        $value = request()?->cookies->get(self::COOKIE);

        return is_string($value) ? $value : null;
    }

    /**
     * The cookie must be exempt from encryption for the banner's JavaScript to
     * be able to write a value Laravel can read back. Registered in
     * bootstrap/app.php.
     */
    public static function cookieName(): string
    {
        return self::COOKIE;
    }
}
