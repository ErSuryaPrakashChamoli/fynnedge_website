---
paths:
  - resources/css/app.css
---

# Resources Css

## No @source for compiled views; ink-faint is AA; html reserves the scrollbar gutter
2026-10-31. (1) app.css must not @source storage/framework/views: it pulled in whatever was compiled at build time (Filament, error/health pages), so one commit built 116 KB or 131 KB of CSS. Tailwind already scans resources/ and app/. (2) --color-ink-faint is #5c687a light / #8a93a3 dark (was #838fa1 / #6c7789, 2.9–3.3:1): it must stay >= 4.5:1 on bg, surface, surface-2 and accent-soft and lighter than ink-muted. (3) `html { scrollbar-gutter: stable }` stops the 0.09 desktop CLS when a long page's scrollbar appears mid-load. Pinned by tests/Feature/Theme/SiteStylesheetTest.php.
