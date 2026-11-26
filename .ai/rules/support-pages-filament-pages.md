---
paths:
  - 'resources/views/debt-consolidation/**,app/Http/Controllers/DebtConsolidationPageController.php,app/Support/Pages/DebtConsolidationPageContent.php,app/Filament/Pages/DebtConsolidationPagesSettings.php'
---

# Support Pages Filament Pages

## "Calculator" means only the Savings Calculator; /debt-consolidation/calculator is the Debt Analyser
The user found two tools both called "calculator" confusing (2026-11-26). The quick tool under /calculators/debt-consolidation is the "Debt Consolidation Savings Calculator". The loan-by-loan suite at /debt-consolidation/calculator (page key `calculator`, ⚡debt-consolidation-calculator) is the "Debt Consolidation Analyser": nav pill "Debt analyser", CTA "Analyse my loans", card "Debt analyser". Its address and page key stay `calculator` (URL and slug unchanged). Never label the analyser "Calculator" again.
Every DC page links the Savings Calculator: a highlighted "Savings calculator" pill placed just before the analyser pill in the page nav (DebtConsolidationPageController::navigation, `tool` => true), the first tools card (savings_card_* settings, shown whenever the `tools` section is on, because that page is always reachable), and a "Only need a quick estimate?" line above the analyser. Pinned by DebtConsolidationPagesTest.
