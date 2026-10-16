---
paths:
  - 'app/Modules/Experian/**'
---

# Experian

## Experian is a separate module; the PDF is its own paid product
Experian (Surepass credit-report-experian) lives in app/Modules/Experian with its own table experian_credit_requests (product score|report|pdf), policy, admin group "Experian", API routes/experian.php (throttle experian-api) and settings experian_*. Never mix it into the CRIF or Equifax classes. Start paid pulls only via RequestExperianCheck / ExperianCreditService::request. Payload is {name, pan, mobile, consent:"Y"}: no gender or id_type, and Surepass does not validate consent, so ExperianSubject (Given consent only) is the guard. A PDF is bought only as product "pdf". The job downloads its temporary link once, without the token, into encrypted private storage. FetchExperianPdf never calls a paid endpoint. Reuse: a score is answered by any product; a report only by a report; a PDF only by a PDF with a stored file. The token comes from ExperianSettings::surepassClient() (SUREPASS_EXPERIAN_TOKEN, else the shared token). The plan on 2026-10-16 allowed fetch-report but NOT score or fetch-report-pdf; run `php artisan experian:check-access`.
