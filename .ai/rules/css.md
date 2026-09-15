---
paths:
  - 'resources/views/components/site/promo-bar.blade.php,resources/js/app.js,resources/css/app.css'
---

# Css

## Promo bar scroll trigger is two-way and must ignore its own body padding
The promoBar Alpine component shows the bar while scroll depth ≥ triggerValue% and hides it again above that limit (delay/exit-intent bars stay up once shown). While up, --promo-bar-offset pads <body>, which grows scrollHeight: the percentage MUST subtract `this.offset` (set in reportHeight) or the bar hides the instant it rises and flickers at the limit. The pop-up is an edge-to-edge panel flush with the bottom of the screen (.promo-bar-surface, rounded top corners only); its content row is a centred max-w-7xl grid with named areas ("media meta meta"/"media msg cta" on phones, "media meta cta"/"media msg cta" from sm) so the badge/countdown row gets the full width on phones; column spacing is margins on media/cta, not gap, so a hidden medallion leaves no empty gap.
