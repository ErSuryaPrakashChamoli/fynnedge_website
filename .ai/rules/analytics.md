---
paths:
  - 'app/Support/Analytics/**'
---

# Analytics

## Tag scripts download via fynnedgeLoadTag: first interaction or 4s after load
Decided by the user 2026-11-03: GTM/GA4/Meta blocked a phone's main thread 1.0–2.2s on every page (Lighthouse mobile). head() renders tagLoader() (window.fynnedgeLoadTag) before the tags, only when at least one generated tag renders. Each tag sets up its queue at once (dataLayer push, gtag('config'), fbq('init'/'PageView')) but its <script src> insertion runs inside fynnedgeLoadTag(function () {...}), released on the first window scroll/pointerdown/pointermove/keydown/touchstart or setTimeout 4000ms after `load`. Accepted cost: a visitor who leaves within ~4s without touching the page is not counted. A new tag must insert its script the same way. Scroll is listened on window without capture on purpose: the social videos row scrolls itself and must not release the tags. Admin custom_* scripts are NOT deferred. Pinned by TrackingScriptsTest.
