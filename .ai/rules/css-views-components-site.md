---
paths:
  - 'resources/css/app.css,resources/views/components/site/header.blade.php'
---

# Css Views Components Site

## Strip "Free assessment" chip pops on a loop by request
The user asked (2026-11-26) for the header strip's Free assessment chip (.dc-strip-cta) to "pop up, appear and disappear" to stand out. Every 3.2s it scales 1 → 1.14 → 0.95 → 1 (dc-strip-cta-pop), the gold fill fades in and out (::before, dc-strip-cta-fill) and a gold halo ripples out (::after, dc-strip-cta-halo). The text never fades, so the button stays readable and clickable. Hover and :focus-visible stop the loop and show the plain hover fill. prefers-reduced-motion turns it off. Don't remove it as "noise". Pinned by DebtConsolidationSitePreferenceTest.
