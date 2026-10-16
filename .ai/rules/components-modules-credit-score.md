---
paths:
  - 'resources/views/components/⚡credit-score-check.blade.php,app/Modules/CreditScore/**'
---

# Components Modules Credit Score

## Credit score check asks for the name the way each bureau API takes it
Surepass's CRIF API needs first_name and last_name apart; CIBIL, Experian and Equifax take one `name`. BureauName::takesSplitName() (true only for CRIF) decides the form: the CRIF page has firstName (required), middleName (optional), lastName (required); every other page has one fullName box (required) — since 2026-10-16. All are title-cased by PanName::normalize() (server, in updated()) and formatPanNameInput() (app.js, while typing). credit_score_checks stores full_name always, and first_name/middle_name/last_name only from the CRIF page (null otherwise). SurepassCrifScoreProvider sends CreditScoreCheck::givenNames() (first + middle) as first_name — same as the admin CrifReport form. Never split a single full-name box into first/last: that broke two-word surnames ("Sen Gupta").
