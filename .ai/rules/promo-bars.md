---
paths:
  - 'resources/views/components/site/promo-bar.blade.php,resources/js/app.js,app/Models/PromoBar.php,app/Filament/Resources/PromoBars/**'
---

# Promo Bars

## Promo bar is full-width, uncloseable and stays up once risen
By owner decision (2026-09-15) the promo bar has NO close button, spans the full viewport width (content centred in max-w-7xl), rises with a "sunrise" glow (.promo-bar-sunrise in app.css), and stays visible to the bottom. It no longer hides while the footer is on screen. Instead, body gets padding-bottom: var(--promo-bar-offset) so the footer's legal copy scrolls clear. Do not re-add dismiss/localStorage or footer-hiding.
The Scroll trigger fires on the first move down the page (PromoBarTrigger::Scroll maxValue 0). trigger_value is only asked for Delay. The reshow_after_hours column is legacy: it's not in the form or clientConfig(). The bar only leaves when its countdown expires.
