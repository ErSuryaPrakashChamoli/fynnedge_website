<?php

namespace App\Support\Analytics;

use App\Models\Setting;
use App\Support\Privacy\CookieConsent;

/**
 * Every global analytics, verification and tracking tag the public site emits,
 * assembled from Settings (Admin → Website Settings → SEO & Tracking).
 *
 * Nothing here is hardcoded in a Blade file and nothing else may render these
 * strings: `x-layouts.app` is the single render site (fed by the View::composer
 * in AppServiceProvider), which is what makes "a tag can never appear twice on
 * a page" true by construction rather than by convention. Each generated
 * snippet additionally guards itself with a `window` flag, so even a second
 * copy pasted into the custom scripts cannot boot the same container twice.
 *
 * Three rules hold for every tag added here:
 *
 *  1. IDs are matched against a strict format before they are interpolated.
 *     That check is the injection guard, not the Filament form's validation —
 *     Setting::set() stores whatever it is given. An unrecognised ID renders
 *     nothing at all rather than a broken tag.
 *  2. A tag's origins must be added to cspSources(), or SecurityHeaders' CSP
 *     blocks it silently — no failed request, just missing data weeks later.
 *  3. Analytics and marketing tags render only when CookieConsent allows their
 *     category. Blocking happens here, server-side, so an unconsented tag is
 *     never in the HTML at all.
 *
 * The custom_* script bodies are the deliberate exception to rule 1: they are
 * raw HTML written by a super admin and are echoed unescaped (escaping them
 * would make every tracking tag inert). See App\Filament\Pages\SeoAnalytics.
 */
class TrackingScripts
{
    private const MEASUREMENT_ID_PATTERN = '/^G-[A-Z0-9]{4,20}$/i';

    private const CONTAINER_ID_PATTERN = '/^GTM-[A-Z0-9]{4,20}$/i';

    private const ADS_CONVERSION_ID_PATTERN = '/^AW-[0-9]{6,15}$/i';

    private const CLARITY_PROJECT_ID_PATTERN = '/^[a-z0-9]{5,20}$/i';

    private const META_PIXEL_ID_PATTERN = '/^[0-9]{10,20}$/';

    private const LINKEDIN_PARTNER_ID_PATTERN = '/^[0-9]{4,12}$/';

    private const VERIFICATION_TOKEN_PATTERN = '/^[A-Za-z0-9_\-]{10,128}$/';

    // ---------------------------------------------------------------- IDs

    public static function googleAnalyticsMeasurementId(): ?string
    {
        return Setting::get('google_analytics_enabled', false)
            ? self::matching(Setting::get('google_analytics_measurement_id'), self::MEASUREMENT_ID_PATTERN)
            : null;
    }

    public static function googleTagManagerContainerId(): ?string
    {
        return Setting::get('google_tag_manager_enabled', false)
            ? self::matching(Setting::get('google_tag_manager_container_id'), self::CONTAINER_ID_PATTERN)
            : null;
    }

    public static function googleAdsConversionId(): ?string
    {
        return self::matching(Setting::get('google_ads_conversion_id'), self::ADS_CONVERSION_ID_PATTERN);
    }

    public static function clarityProjectId(): ?string
    {
        return self::matching(Setting::get('microsoft_clarity_project_id'), self::CLARITY_PROJECT_ID_PATTERN);
    }

    public static function metaPixelId(): ?string
    {
        return self::matching(Setting::get('meta_pixel_id'), self::META_PIXEL_ID_PATTERN);
    }

    public static function linkedInPartnerId(): ?string
    {
        return self::matching(Setting::get('linkedin_partner_id'), self::LINKEDIN_PARTNER_ID_PATTERN);
    }

    public static function googleSearchConsoleToken(): ?string
    {
        return self::verificationTokenFrom(Setting::get('google_search_console_verification'));
    }

    public static function bingWebmasterToken(): ?string
    {
        return self::verificationTokenFrom(Setting::get('bing_webmaster_verification'));
    }

    public static function facebookDomainToken(): ?string
    {
        return self::verificationTokenFrom(Setting::get('facebook_domain_verification'));
    }

    public static function pinterestToken(): ?string
    {
        return self::verificationTokenFrom(Setting::get('pinterest_verification'));
    }

    /**
     * The verification token in an admin-supplied value, or null if there isn't
     * a plausible one in it. Shared by the SEO & Tracking form — which both
     * validates and stores through this, so what reaches the settings table is
     * always the bare token — and by the rendering path below.
     */
    public static function verificationTokenFrom(mixed $value): ?string
    {
        return self::matching(self::normalizeVerificationToken($value), self::VERIFICATION_TOKEN_PATTERN);
    }

    // ------------------------------------------------------------ Rendering

    /**
     * Rendered inside `<head>`: verification first (never consent-gated — a
     * verification tag identifies the site owner and sets no cookies), then the
     * analytics containers, then marketing tags, then admin-authored markup.
     */
    public static function head(): string
    {
        return self::join([
            self::verificationMetas(),
            self::customStyles(),
            self::analyticsAllowed() ? self::tagManagerHead() : '',
            self::analyticsAllowed() ? self::googleAnalytics() : '',
            self::analyticsAllowed() ? self::clarity() : '',
            self::marketingAllowed() ? self::metaPixel() : '',
            self::marketingAllowed() ? self::linkedInInsight() : '',
            self::marketingAllowed() ? self::customScripts('custom_head_scripts') : '',
        ]);
    }

    /**
     * Rendered immediately after the opening `<body>` tag — where the GTM and
     * pixel `<noscript>` fallbacks are required to live.
     */
    public static function bodyStart(): string
    {
        return self::join([
            self::analyticsAllowed() ? self::tagManagerNoscript() : '',
            self::marketingAllowed() ? self::metaPixelNoscript() : '',
            self::marketingAllowed() ? self::linkedInNoscript() : '',
            self::marketingAllowed() ? self::customScripts('custom_body_start_scripts') : '',
        ]);
    }

    public static function bodyEnd(): string
    {
        if (! self::marketingAllowed()) {
            return '';
        }

        return self::join([
            self::customScripts('custom_body_end_scripts'),
            self::customJavaScript(),
        ]);
    }

    /**
     * The origins the enabled tags need in the Content-Security-Policy.
     *
     * Custom scripts contribute the https:// origins that literally appear in
     * the markup an admin pasted — that covers the loader each provider's
     * official snippet fetches, which is the part a 'self' policy blocks
     * outright. A tag that fetches from an origin its own markup never mentions
     * still needs adding here by hand.
     *
     * Consent is deliberately NOT applied to this list: the policy is a header
     * on every response, and narrowing it per visitor would mean a visitor who
     * accepts cookies gets a CSP that was computed before they did.
     *
     * @return array{script: list<string>, connect: list<string>, frame: list<string>, img: list<string>}
     */
    public static function cspSources(): array
    {
        $sources = ['script' => [], 'connect' => [], 'frame' => [], 'img' => []];

        if (self::googleAnalyticsMeasurementId() || self::googleTagManagerContainerId() || self::googleAdsConversionId()) {
            $sources['script'][] = 'https://www.googletagmanager.com';
            $sources['img'][] = 'https://www.googletagmanager.com';
            $sources['img'][] = 'https://*.google-analytics.com';
            $sources['connect'][] = 'https://www.googletagmanager.com';
            $sources['connect'][] = 'https://*.google-analytics.com';
            $sources['connect'][] = 'https://*.analytics.google.com';
        }

        if (self::googleTagManagerContainerId()) {
            $sources['frame'][] = 'https://www.googletagmanager.com';
        }

        if (self::googleAdsConversionId()) {
            $sources['img'][] = 'https://googleads.g.doubleclick.net';
            $sources['frame'][] = 'https://td.doubleclick.net';
            $sources['connect'][] = 'https://googleads.g.doubleclick.net';
        }

        if (self::clarityProjectId()) {
            $sources['script'][] = 'https://www.clarity.ms';
            $sources['connect'][] = 'https://*.clarity.ms';
            $sources['connect'][] = 'https://c.bing.com';
        }

        if (self::metaPixelId()) {
            $sources['script'][] = 'https://connect.facebook.net';
            $sources['img'][] = 'https://www.facebook.com';
            $sources['connect'][] = 'https://www.facebook.com';
        }

        if (self::linkedInPartnerId()) {
            $sources['script'][] = 'https://snap.licdn.com';
            $sources['img'][] = 'https://px.ads.linkedin.com';
            $sources['img'][] = 'https://px4.ads.linkedin.com';
            $sources['connect'][] = 'https://px.ads.linkedin.com';
        }

        $customOrigins = self::customScriptOrigins();

        foreach (['script', 'connect', 'frame', 'img'] as $directive) {
            $sources[$directive] = array_values(array_unique([...$sources[$directive], ...$customOrigins]));
        }

        return $sources;
    }

    private static function analyticsAllowed(): bool
    {
        return CookieConsent::allowsAnalytics();
    }

    private static function marketingAllowed(): bool
    {
        return CookieConsent::allowsMarketing();
    }

    /**
     * @return list<string>
     */
    private static function customScriptOrigins(): array
    {
        $markup = self::join([
            self::customScripts('custom_head_scripts'),
            self::customScripts('custom_body_start_scripts'),
            self::customScripts('custom_body_end_scripts'),
            self::customJavaScript(),
        ]);

        if ($markup === '') {
            return [];
        }

        preg_match_all('~https://[a-z0-9.\-]+[a-z]~i', $markup, $matches);

        return array_values(array_unique($matches[0]));
    }

    private static function verificationMetas(): string
    {
        return self::join([
            self::verificationMeta('google-site-verification', self::googleSearchConsoleToken()),
            self::verificationMeta('msvalidate.01', self::bingWebmasterToken()),
            self::verificationMeta('facebook-domain-verification', self::facebookDomainToken()),
            self::verificationMeta('p:domain_verify', self::pinterestToken()),
            self::otherVerificationMetas(),
        ]);
    }

    private static function verificationMeta(string $name, ?string $token): string
    {
        return $token ? '<meta name="'.$name.'" content="'.e($token).'">' : '';
    }

    /**
     * A free-form slot for verification services this app has no field for.
     * Only `<meta>` tags survive — anything else in the box is dropped rather
     * than rendered, so this can never become a second, ungated way to inject a
     * script into every page.
     */
    private static function otherVerificationMetas(): string
    {
        $value = Setting::get('other_verification_meta');

        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        preg_match_all('/<meta\s+[^<>]*>/i', $value, $matches);

        return implode("\n", array_filter(
            $matches[0],
            fn (string $tag): bool => ! preg_match('/\bon[a-z]+\s*=|javascript:/i', $tag),
        ));
    }

    private static function googleAnalytics(): string
    {
        $id = self::googleAnalyticsMeasurementId();
        $adsId = self::marketingAllowed() ? self::googleAdsConversionId() : null;

        if (! $id && ! $adsId) {
            return '';
        }

        /*
         * GA4 and Google Ads share one gtag.js loader — loading it twice
         * double-counts every pageview — so whichever is configured brings it
         * in and each then registers its own config line.
         */
        $primary = $id ?: $adsId;
        $configs = implode("\n                ", array_map(
            fn (string $target): string => "w.gtag('config', '{$target}');",
            array_filter([$id, $adsId]),
        ));

        return <<<HTML
        <script>
            (function (w, d, id) {
                if (w.fynnedgeGtagLoaded) { return; }
                w.fynnedgeGtagLoaded = true;
                var s = d.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=' + id;
                d.head.appendChild(s);
                w.dataLayer = w.dataLayer || [];
                w.gtag = function () { w.dataLayer.push(arguments); };
                w.gtag('js', new Date());
                {$configs}
            })(window, document, '{$primary}');
        </script>
        HTML;
    }

    private static function tagManagerHead(): string
    {
        $id = self::googleTagManagerContainerId();

        if (! $id) {
            return '';
        }

        return <<<HTML
        <script>
            (function (w, d, s, l, i) {
                if (w.fynnedgeGtmLoaded) { return; }
                w.fynnedgeGtmLoaded = true;
                w[l] = w[l] || [];
                w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '{$id}');
        </script>
        HTML;
    }

    private static function tagManagerNoscript(): string
    {
        $id = self::googleTagManagerContainerId();

        if (! $id) {
            return '';
        }

        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.$id.'"'
            .' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
    }

    private static function clarity(): string
    {
        $id = self::clarityProjectId();

        if (! $id) {
            return '';
        }

        return <<<HTML
        <script>
            (function (c, l, a, r, i) {
                if (c.fynnedgeClarityLoaded) { return; }
                c.fynnedgeClarityLoaded = true;
                c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
                var t = l.createElement(r);
                t.async = 1;
                t.src = 'https://www.clarity.ms/tag/' + i;
                var y = l.getElementsByTagName(r)[0];
                y.parentNode.insertBefore(t, y);
            })(window, document, 'clarity', 'script', '{$id}');
        </script>
        HTML;
    }

    private static function metaPixel(): string
    {
        $id = self::metaPixelId();

        if (! $id) {
            return '';
        }

        return <<<HTML
        <script>
            (function (f, b, e, v) {
                if (f.fynnedgeMetaPixelLoaded) { return; }
                f.fynnedgeMetaPixelLoaded = true;
                var n = f.fbq = function () {
                    n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
                };
                if (!f._fbq) { f._fbq = n; }
                n.push = n;
                n.loaded = true;
                n.version = '2.0';
                n.queue = [];
                var t = b.createElement(e);
                t.async = true;
                t.src = v;
                var s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s);
                f.fbq('init', '{$id}');
                f.fbq('track', 'PageView');
            })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
        </script>
        HTML;
    }

    private static function metaPixelNoscript(): string
    {
        $id = self::metaPixelId();

        if (! $id) {
            return '';
        }

        return '<noscript><img height="1" width="1" style="display:none" alt=""'
            .' src="https://www.facebook.com/tr?id='.$id.'&ev=PageView&noscript=1"></noscript>';
    }

    private static function linkedInInsight(): string
    {
        $id = self::linkedInPartnerId();

        if (! $id) {
            return '';
        }

        return <<<HTML
        <script>
            (function (w, d) {
                if (w.fynnedgeLinkedInLoaded) { return; }
                w.fynnedgeLinkedInLoaded = true;
                w._linkedin_data_partner_ids = w._linkedin_data_partner_ids || [];
                w._linkedin_data_partner_ids.push('{$id}');
                var s = d.createElement('script');
                s.async = true;
                s.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';
                d.getElementsByTagName('script')[0].parentNode.appendChild(s);
            })(window, document);
        </script>
        HTML;
    }

    private static function linkedInNoscript(): string
    {
        $id = self::linkedInPartnerId();

        if (! $id) {
            return '';
        }

        return '<noscript><img height="1" width="1" style="display:none" alt=""'
            .' src="https://px.ads.linkedin.com/collect/?pid='.$id.'&fmt=gif"></noscript>';
    }

    private static function customStyles(): string
    {
        $css = self::customScripts('custom_css');

        return $css === '' ? '' : '<style>'.$css.'</style>';
    }

    private static function customJavaScript(): string
    {
        $js = self::customScripts('custom_javascript');

        return $js === '' ? '' : '<script>'.$js.'</script>';
    }

    private static function customScripts(string $key): string
    {
        $value = Setting::get($key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Google's own instructions show the verification tag, not the bare token,
     * so accept either and keep only the token.
     */
    private static function normalizeVerificationToken(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/content=["\']([^"\']+)["\']/i', $value, $matches)) {
            return trim($matches[1]);
        }

        return $value;
    }

    private static function matching(mixed $value, string $pattern): ?string
    {
        return is_string($value) && preg_match($pattern, trim($value)) ? trim($value) : null;
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function join(array $parts): string
    {
        return trim(implode("\n", array_filter($parts, fn (string $part) => $part !== '')));
    }
}
