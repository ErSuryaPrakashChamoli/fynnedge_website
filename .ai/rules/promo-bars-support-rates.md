---
paths:
  - 'app/Filament/Resources/PromoBars/**,app/Support/Rates/RateText.php'
---

# Promo Bars Support Rates

## Promo bar Active switch; typed-rate errors suggest the token
The user decided on 2026-11-02 to keep the typed-rate rule. Bars saved before RateText::rule() existed could not be saved at all, not even to switch them off. Migration 2026_11_02_055940 rewrote their headline, rotating messages and the _hi versions with RateText::tokenised() as [rate:personal-loan]. Banners were deliberately NOT converted, because a banner's product slug can't be guessed. The rule's error message now shows the tokenised line, plus "change the rate under Catalog → Interest Rates". PromoBarsTable has a non-toggleable "Active" ToggleColumn that maps on/off to status Published/Draft. It saves without form validation, so it is disabled unless the user can('update').
