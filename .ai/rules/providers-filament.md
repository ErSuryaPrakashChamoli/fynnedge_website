---
paths:
  - 'app/Http/Middleware/SecurityHeaders.php,app/Providers/Filament/AdminPanelProvider.php'
---

# Providers Filament

## CSP is live — script-src needs 'unsafe-eval' for Alpine, and Filament panels need the middleware added explicitly
SecurityHeaders now sets a real Content-Security-Policy (added 2026-08-29, superseding the earlier "deliberately not set" rule). default-src/script-src/style-src/font-src are all 'self' since every asset is Vite-bundled with zero external CDN references anywhere in the codebase (verified by grep). script-src keeps 'unsafe-eval' for Alpine's `new Function()`-based expression evaluation; style-src keeps 'unsafe-inline' for Livewire's injected <style> block and two progress-bar views' inline `style="width:...%"`. img-src allows https: so admin-authored rich-text body content can still reference external images.

Load-bearing gotcha: Filament panels do NOT inherit the global `web` middleware group — AdminPanelProvider declares its own explicit ->middleware([...]) array. Appending SecurityHeaders to the web group alone (as originally done in Phase 13) silently never applied it to any /admin/* route. SecurityHeaders::class must be listed directly in AdminPanelProvider's middleware array too. Caught only because a same-CSP-on-admin-and-public test was added — the four non-CSP security headers from Phase 13 had this exact gap the whole time and nothing caught it until now.

Verification limits: confirmed via curl that the header is present and every route (public + admin + a live journey session) still returns 200 with the policy applied. That proves no server-side breakage. It does NOT prove client-side JS/Alpine/Livewire behavior is unaffected — no browser automation tool exists in this environment. If Alpine/Livewire interactivity silently breaks under this policy, it'll only show as a browser console error, not a failed request.

## script-src needs 'unsafe-inline' too — Filament ships inline bootstrapping <script> blocks
script-src carries 'self' 'unsafe-eval' 'unsafe-inline'. The 'unsafe-inline' is load-bearing: Filament's own panel views (e.g. the sidebar) inject inline <script> blocks — e.g. to seed the collapsed-navigation-group state into localStorage before Alpine boots. Without it, the browser silently drops the inline script (no network-level failure — only a CSP console warning plus a downstream swallowed Alpine expression error), leaving Alpine's sidebar store `null` instead of an array. Every `.includes()` call on it then throws, so navigation groups render with zero visible items and their collapse/expand buttons do nothing.
Confirmed live: after adding a `->navigationGroups()` config to AdminPanelProvider, the Catalog/Content sidebar groups appeared empty and unclickable even after a hard refresh, until 'unsafe-inline' was added to script-src in SecurityHeaders::csp(). Same fix must travel with any future CSP tightening of script-src.
