---
paths:
  - 'resources/css/**,resources/views/**'
---

# Views

## Theming is CSS-variable-based, not `dark:` utility variants
All design tokens (--color-bg, --color-surface, --color-ink, --color-accent, --color-pass, --color-warn, --color-line, plus -muted/-soft/-strong variants) are Tailwind v4 @theme colors defined once in resources/css/app.css. Dark mode is handled by redefining those same custom properties under `@media (prefers-color-scheme: dark)` and `:root[data-theme="dark"]` — never by sprinkling `dark:bg-*` classes on components. Build every new component/page against the token utilities (bg-bg, text-ink, bg-accent, border-line, etc.) and it gets both themes for free.
