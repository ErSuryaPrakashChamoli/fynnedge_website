---
paths:
  - 'app/Filament/Resources/PromoBars/**,app/Support/Rates/RateText.php'
---

# Promo Bars Support Rates

## Promo bars may type rates by hand; Active switch in the list
The owner decided on 2026-11-02, after being told the risks (stale figures, rates that don't match other pages, misleading-ad exposure, no evidence trail), to type promo bar rates by hand. Do NOT add RateText::rule() back to PromoBarForm's headline, rotating_messages or their _hi fields. [rate:…] tokens still work there. Banners, LoanProduct and LenderProduct keep the rule. Earlier the same day, migration 2026_11_02_055940 rewrote the typed rates in existing bars to [rate:personal-loan]; it has already run in production, so leave it as it is. The rule's error message (banners etc.) shows the line rewritten by RateText::tokenised(). PromoBarsTable has a non-toggleable "Active" ToggleColumn that maps on/off to status Published/Draft. It saves without form validation, so it is disabled unless the user can('update').
