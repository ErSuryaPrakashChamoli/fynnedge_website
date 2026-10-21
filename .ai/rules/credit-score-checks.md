---
paths:
  - 'app/Filament/Resources/CreditScoreChecks/**'
---

# Credit Score Checks

## Credit Score Checks dates are India time end to end; never whereDate() there
User decision (2026-10-21): the "Date of application (IST)" column, the date filter and the CSV all use CreditScoreChecksCsv::TIMEZONE (Asia/Kolkata), while the app timezone is UTC. The filter converts each picked day to UTC start/end-of-day bounds; whereDate('created_at') would compare UTC dates and drop or add checks made between 00:00 and 05:30 IST. Filters sit above the table (bureau multi-select, status, date, score range) and both "Download CSV" (header, filtered) and "Download selected (CSV)" (bulk) go through CreditScoreChecksCsv::download. Pinned by CreditScoreChecksTableTest.
