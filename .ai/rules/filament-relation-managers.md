---
paths:
  - 'app/Filament/Resources/CreditScoreChecks/**,app/Filament/Resources/CibilCreditRequests/**,app/Filament/Pages/CibilDashboard.php,app/Filament/RelationManagers/CreditScoreChecksRelationManager.php'
---

# Filament Relation Managers

## CIBIL-page stand-in reports stay Experian; CIBIL admin screens only link to them
User decision (2026-10-19): a Free CIBIL Score page check answered by the Experian stand-in is stored ONLY as an experian_credit_requests row, so CIBIL Credit Reports/Dashboard showed nothing and admins thought the report was missing. Never copy or relabel it into CIBIL. Instead: CreditScoreCheckResource::bureauReportUrl() (follows CreditScoreCheck::bureauRequest(), checks `view` on the bureau request) drives the "Report"/"View Experian report" action on Credit Score Checks and Customer → Website score checks; CibilCreditRequestResource::experianStandInReportCount() (genuine, answeredBy(Cibil, Experian), reused_from null) drives the CIBIL list's header button and the dashboard callout, shown only to users who can viewAny ExperianCreditRequest. Pinned by LiveCibilScoreCheckTest "links the CIBIL page's Experian report…".
