---
paths:
  - resources/views/components/⚡credit-score-check.blade.php
---

# Resources Views Components

## Livewire OTP state must be #[Locked]
Every public Livewire property is writable from the browser unless #[Locked]. The credit-score check's `step`, `otpChallengeId` and `verifiedMobileNumber` are locked — before 2026-10-13 a visitor could set step='details' and skip the OTP. submitDetails() re-checks and consumeFor()s the challenge and runs the check against verifiedMobileNumber, never the wire:model'd mobileNumber. Apply the same to any Livewire component gating on verification. Pinned by CreditScoreCheckFlowTest.
