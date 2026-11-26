---
paths:
  - 'app/Support/Calculators/CalculatorCatalog.php,app/Http/Controllers/CalculatorController.php'
---

# Calculators Http Controllers

## Debt Consolidation Savings Calculator is public only while the DC service is live
/calculators/debt-consolidation 404s and CalculatorCatalog::groups() (menus, /calculators, sitemap, homepage calculator count) leaves it out unless DebtConsolidationSettings::enabled(). Its copy is DC customer wording awaiting Legal/Compliance approval (BV-18/LA-02), so deploying with the service OFF must not publish it (owner instruction 2026-11-26). Admin lists use CalculatorCatalog::pages() = groups(includeHidden: true), so its About text and indexing can be prepared while it is off and a saved "hide from search" choice for it stays a valid option (otherwise Calculators Page settings fail to save). DC pages link it only while the service is live, including signed previews. Pinned by DebtConsolidationSavingsCalculatorComponentTest "stays off the site…" and CalculatorPagesSettingsTest "still saves while…".
