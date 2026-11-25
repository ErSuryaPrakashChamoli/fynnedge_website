---
paths:
  - resources/css/filament/admin/theme.css
---

# Css Filament Admin

## Custom scrollbars must reset scrollbar-width/color to auto
theme.css sets scrollbar-width/scrollbar-color on `.fi-body *`. Chrome 121+ (and Edge) IGNORE ::-webkit-scrollbar styles on any element where either standard property is not `auto`. So a styled scrollbar (e.g. the dashboard "Jump to" dock, .fe-jump-scroller, with its gradient thumb) must set both back to `auto` and add a Firefox fallback under `@supports not selector(::-webkit-scrollbar)` (solid colours only). Headless Chromium started with --hide-scrollbars shows no scrollbar at all, so drop that flag when checking one.
