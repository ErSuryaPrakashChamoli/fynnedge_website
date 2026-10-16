---
paths:
  - 'app/Modules/CreditScore/Providers/**'
---

# Credit Score Providers

## Public live score checks are recorded as bureau requests (channel "website")
Since 2026-10-16 the public Equifax and Experian score providers (the CIBIL page's Experian stand-in included) wrap their paid Surepass call in RecordWebsite{Equifax,Experian}Check. start() runs before the call and creates the bureau request as Processing, with no in-flight key and requested_by null, plus a CreditConsent (method Otp, evidence = the score check's public_id, customer = newest Customer with that phone). finish() stores the answer through Process*Request::record(). The full report lives in the bureau table, and credit_score_checks keeps only score / client_id / message_code / {bureau}_request. Without this the admin dashboards showed 0 although the site paid for full reports (user report, live server). Recording failures are logged and never block the visitor. CRIF's public provider does not do this yet.
