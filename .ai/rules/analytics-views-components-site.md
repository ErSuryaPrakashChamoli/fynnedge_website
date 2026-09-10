---
paths:
  - 'app/Support/Privacy/**,app/Support/Analytics/**,resources/views/components/site/cookie-consent.blade.php'
---

# Analytics Views Components Site

## Consent gating happens server-side, before a tag is ever rendered
CookieConsent reads a first-party, UNENCRYPTED cookie (exempted in bootstrap/app.php's encryptCookies) because the banner writes it from JavaScript — that is the only place the visitor's answer exists. TrackingScripts then omits gated tags from the HTML entirely; nothing is "hidden after loading", because a client-side block has already fetched the third-party JS. The banner reloads the page once on accept, which is what makes the server-rendered decision correct on the next request.

Categories: analytics = GA4, GTM, Clarity. marketing = Meta Pixel, LinkedIn, Google Ads, and everything on the Advanced tab (custom head/body scripts + custom JS). Nothing is gated unless cookie_consent_enabled AND that category's *_consent_required are both on, so an install that has made its own legal assessment keeps today's behaviour.

Deliberately NOT consent-gated: cspSources(). The CSP is a header on every response and computing it per visitor would hand a visitor who just accepted a policy computed before they did. Verification metas are also ungated — they set no cookies.

When adding a tag: give it a strict ID pattern (an unrecognised ID renders nothing), a `window.fynnedge*Loaded` guard, its origins in cspSources(), and a consent category. Missing the CSP entry fails silently — no error, just no data.
