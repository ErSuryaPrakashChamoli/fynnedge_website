---
paths:
  - 'app/Models/Achievement.php,app/Filament/Resources/Achievements/**,resources/views/home.blade.php'
---

# Achievements Views

## Achievements are admin-only; the homepage falls back to derived counts, never to a seeded figure
The homepage hero stat row renders published `Achievement` rows when any exist, and otherwise falls back to the two counts the app derives from real records (active Lenders, published LoanProducts, both with count-up animation). Nothing is ever seeded into the `achievements` table: an unverified headline number must never reach the page on its own, and an empty table must never blank the row. Keep both halves of that `@if/@elseif` — dropping the fallback re-introduces the blank-row case, and seeding the table re-introduces the fabricated-claim case.

`value` is a string, not numeric, so "2.16"/"4.5"/"550" all publish as typed; `prefix`/`suffix` carry the ₹/+/Cr decoration and `displayValue()` composes them. Admin figures are rendered statically (no count-up) because an arbitrary admin string has no integer target to animate toward — don't add parsing to animate them.

Shape follows HowItWorksStep (draft/publish toggle + sort_order, no Publishable scheduling), and the Marketing starter role gets its permissions in RoleSeeder alongside Testimonial/Banner/HowItWorksStep.
