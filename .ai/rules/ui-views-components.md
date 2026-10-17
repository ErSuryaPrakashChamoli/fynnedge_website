---
paths:
  - 'resources/views/components/ui/stepper.blade.php,resources/views/components/⚡credit-score-check.blade.php'
---

# Ui Views Components

## Credit-score check progress is a horizontal arrow chart, not numbered steps
Since 2026-10-17 the user wants the credit-score check's progress shown as horizontal chevrons with no 1-2-3-4 numbers. x-ui.stepper variant="arrows" does this: one row when its @container is at least 30rem wide, two rows of two below that, and labels wrap to 2 lines. Done steps show a tick, the active step has a pulsing dot and the promo-bar sweep, and upcoming steps show an empty ring. The shape and colours are .step-arrow* in app.css and use theme tokens only. The default "list" variant stays vertical for the journey sidebar (220px column) and the home "How it works" fallback, so don't switch those to arrows. Pinned by CreditScoreCheckFlowTest "arrow chart".
