---
paths:
  - 'app/Modules/Wallet/**,resources/views/components/⚡credit-score-check.blade.php,app/Http/Controllers/Portal/WalletController.php'
---

# Controllers Portal

## Paid refreshes: first free, then ₹99/₹79 + GST from a closed wallet, charged only for a new answer
User decision 2026-10-17 (docs/customer-portal.md §7). CreditCheckBilling decides in order: billing off/demo → none; stored score in reuse window → none; free (first check, then 6 months after the last free one, per customer AND per PAN; billing-null legacy checks count as free) → plan (6m ₹150 / 1y ₹250, every bureau once per 30 days) → wallet (₹99 first paid refresh, ₹79 after), GST 18% ADDED on top. The component quotes under a per-customer cache lock, stops BEFORE spending the OTP if the wallet is short, holds the amount, then captures only for a new bureau answer (failed/uncertain/reused → release). All money in integer paise via Wallet (row-locked ledger, never negative). CUSTOMER_BILLING_ENABLED defaults false until a PaymentGateway exists; never mark a top-up paid except via CompleteWalletTopUp after server-side gateway verification.
