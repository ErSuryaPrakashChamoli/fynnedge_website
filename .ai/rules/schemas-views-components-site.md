---
paths:
  - 'app/Support/Enquiries/EnquiryFormContent.php,app/Filament/Resources/MarketingSections/Schemas/MarketingSectionForm.php,resources/views/components/site/quick-enquiry.blade.php'
---

# Schemas Views Components Site

## Quick Enquiry box note lives in the marketing section's subheading column
The small line under the homepage Quick Enquiry phone field ("We only need your number to call you back…") is `EnquiryFormContent::forQuickEnquiry()['note']`, resolved from the `home_quick_enquiry` MarketingSection's `subheading` column (the box has no other subheading, so the column was reused instead of adding one). Blank falls back to the built-in wording. MarketingSectionForm's subheading helper text explains this for that placement. Pinned by EnquiryFormContentTest.
