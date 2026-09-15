---
paths:
  - 'app/Models/PromoBar.php,app/Support/PromoBars/**,app/Filament/Resources/PromoBars/**,resources/views/components/site/promo-bar.blade.php'
---

# Promo Bars Views Components Site

## Promo bar: one per page, chosen and sanitised server-side
PromoBars::forCurrentPage() picks ONE bar: placements use the FAQ token vocabulary + PromoBars::EVERY_PAGE ('all'); a bar is skipped if excluded_placements lists the page, its button links to the current path, or its stored cta_url fails PromoBar::CTA_URL_PATTERN; a bar pinned to the page by name beats a site-wide one, then sort_order. Copy is rendered server-side (x-show only); *starred* words go through PromoBar::emphasise(), which escapes BEFORE adding <strong>. Colours re-validated via SiteThemeStyles::hexColor() in barStyle(). CSS fallbacks in app.css are fixed brand hex, not --color-* tokens (dark-theme accents are light). The Alpine `promoBar` component (resources/js/app.js) sets --promo-bar-offset on <html>; other bottom widgets (video bubble) use it as margin-bottom to stay above the bar. Images live in promo-bars/ and are registered in MediaRegistry. Placement scope is shared via the HasPagePlacements trait.
