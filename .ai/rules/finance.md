---
paths:
  - 'app/Modules/DebtConsolidation/Services/Finance/**'
---

# Finance

## PHP 8.4+ round() rounds half-paisa ties down for computed floats — use the paise helpers
round(135317 * 0.015, 2) gives 2029.75, not 2029.76: since PHP 8.4 round() uses the float's exact binary value (2029.7549999…). Interest and percentage charges must use Amortisation::interest() / Money::percentage(), which compute in integer paise with half-up division for rates with up to 4 decimals. Found by comparing with a Python Decimal reference (1-paisa drift over a 36-month schedule).
