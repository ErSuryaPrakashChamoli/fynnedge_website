---
paths:
  - 'resources/views/components/⚡credit-score-check.blade.php,app/Modules/CreditScore/**'
---

# Components Modules Credit Score

## Credit score check takes first/middle/last name in separate boxes
Surepass's CRIF API needs first_name and last_name apart; the public check used to split one "full name" box on the last space, which broke two-word surnames ("Sen Gupta"). Since 2026-10-15 the form has firstName (required), middleName (optional), lastName (required) on every bureau page, each title-cased by PanName::normalize() (server, in updated()) and formatPanNameInput() (app.js, while typing). credit_score_checks stores first_name/middle_name/last_name plus full_name (joined, for admin display/search). SurepassCrifScoreProvider sends CreditScoreCheck::givenNames() (first + middle) as first_name — same as the admin CrifReport form. Never go back to splitting full_name.
