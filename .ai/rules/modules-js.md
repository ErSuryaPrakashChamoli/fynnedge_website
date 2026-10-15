---
paths:
  - 'app/Http/Controllers/**,app/Modules/**,resources/js/app.js'
---

# Modules Js

## Public requests must save and report English, never the visitor's Hindi
A Hindi visitor's request reads translated model values, so any value copied from a translatable attribute into storage or analytics must use getRawOriginal('col') (or an enum/slug). Known cases already handled: LoanEnquiryController enquiry_source, RecordCreditConsent purpose, EligibilityEngine reason snapshots (display goes through EligibilityResultReason::visitorMessage()), the calculator's prefilled expert-contact message, and loan-enquiry-form's `analyticsName` sent to analytics as productName. Journey responses store option VALUES; options_hi only relabels by value. Tests posting JSON need withCredentials() or the locale cookie is not sent.
