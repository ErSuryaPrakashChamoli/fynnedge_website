---
paths:
  - 'app/Support/Analytics/**,app/Filament/Pages/SeoAnalytics.php,app/Http/Middleware/SecurityHeaders.php'
---

# Http Middleware

## Analytics tags render from one place and must be added to the CSP
GA4, GTM, the Search Console meta and the three custom script slots all come from Settings via App\Support\Analytics\TrackingScripts, rendered ONLY by x-layouts.app (View::composer in AppServiceProvider supplies $trackingHead/$trackingBodyStart/$trackingBodyEnd). Never render them from another view or hardcode an ID in Blade — one render site is what makes "a tag can't appear twice on a page" true by construction. IDs are re-validated against a strict pattern inside TrackingScripts before interpolation, exactly like SiteThemeStyles' allow-list: Setting::set() stores whatever it's given, so the form's validation is not the injection guard.

Trap: the CSP in SecurityHeaders is script-src 'self'. Any new third-party tag needs its origin in TrackingScripts::cspSources() or the browser blocks it SILENTLY — no failed request, no server error, just missing analytics weeks later. cspSources() adds Google's origins when GA/GTM are on, plus the https:// origins that literally appear in an admin's pasted custom scripts; a tag that fetches from an origin its own markup never mentions must be added by hand.

custom_head_scripts / custom_body_start_scripts / custom_body_end_scripts are echoed unescaped (escaping makes tracking tags inert), so the Custom tracking section is visible only to super_admin, on top of the page's own View:SeoAnalytics permission. Filament does not dehydrate hidden components, so a non-super-admin saving the page leaves those settings untouched rather than blanking them — keep that property if the section is ever restructured.
