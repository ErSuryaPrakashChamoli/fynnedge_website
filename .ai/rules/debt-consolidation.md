---
paths:
  - 'app/Modules/DebtConsolidation/**'
---

# Debt Consolidation

## Every debt consolidation figure comes from the engine and is stored as a snapshot
Calculators, the journey, admin tabs, the portal, PDFs and the API never compute money themselves: they call DebtConsolidationEngine::assess() (pure PHP, no DB/clock/config) and, where kept, store a DebtCalculationSnapshot (inputs, results, FormulaVersion, scoring version, input hash). Snapshots are immutable and reports render only from them. Any change that can alter a figure for the same inputs must bump FormulaVersion::CURRENT and add a row to docs/debt-consolidation/06-financial-engine.md §9. Unit-test expected values must be derived independently (by hand or a separate Decimal implementation), never with engine code.
