---
paths:
  - 'app/Modules/Customers/Services/CustomerCreditReports.php,app/Modules/CreditScore/Actions/SubmitCreditScoreCheck.php'
---

# Credit Score Actions

## Portal refresh policy approved: 30 days per bureau, from the existing reuse period
User decision (2026-10-16): the customer portal's monthly credit-score refresh period IS each bureau's existing public reuse period (SubmitCreditScoreCheck::reuseDays(): CIBIL publicScoreReuseDays, CRIF reuseDays, Equifax/Experian scoreReuseDays; 30 days by default). Do not add a separate portal refresh setting; changing a bureau's reuse days in its Settings page changes its portal refresh period too.
