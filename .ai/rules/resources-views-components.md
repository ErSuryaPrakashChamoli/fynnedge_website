---
paths:
  - resources/views/components/⚡credit-score-check.blade.php
  - resources/views/components/⚡debt-consolidation-savings-calculator.blade.php
---

# Resources Views Components

## Livewire OTP state must be #[Locked]
Every public Livewire property is writable from the browser unless #[Locked]. The credit-score check's `step`, `otpChallengeId` and `verifiedMobileNumber` are locked — before 2026-10-13 a visitor could set step='details' and skip the OTP. submitDetails() re-checks and consumeFor()s the challenge and runs the check against verifiedMobileNumber, never the wire:model'd mobileNumber. Apply the same to any Livewire component gating on verification. Pinned by CreditScoreCheckFlowTest.

## The quick debt consolidation calculator's "saving" is the total-cost difference, never the EMI drop
The owner asked (2026-11-26) for a calculator shaped like Loansjagat's: sliders, an EMI bar chart, and a "Savings %" card. Their percentage is only the EMI reduction. Here, "Total saving" appears only when the engine's netFinancialBenefit is positive; otherwise the card reads "Extra cost", and the EMI change is labelled as a monthly change. Never relabel the EMI percentage as a saving: the mandate forbids claiming savings from a lower EMI.

The calculator must keep running DebtConsolidationEngine through CaseInputBuilder::forCalculator, never its own formula.

Test figures come from docs/debt-consolidation/validation/reference.py.

If the config is cached, run its tests with APP_CONFIG_CACHE/APP_ROUTES_CACHE/APP_EVENTS_CACHE pointed at non-existent files. The owner runs `php artisan optimize`.
