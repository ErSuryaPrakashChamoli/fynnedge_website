---
paths:
  - 'app/Support/Enquiries/QuickEnquiryPageContent.php,app/Filament/Pages/QuickEnquiryPageSettings.php,resources/views/quick-enquiry.blade.php'
---

# Pages Views

## Quick Enquiry page copy is one Setting, edited on its own admin page
Everything on /quick-enquiry (meta title/description, hero badge/headline/accent/intro, highlights list, steps list, lender strip toggle+label, form eyebrow/no-product headline/submit label, explore heading/description/cards) plus the homepage "Quick Enquiry" button label lives in ONE Setting key `quick_enquiry_page`, resolved by App\Support\Enquiries\QuickEnquiryPageContent::resolve() and edited at Admin → Website Settings → Quick Enquiry Page (QuickEnquiryPageSettings, permission View:QuickEnquiryPageSettings, granted to Marketing in RoleSeeder).
Fallback contract: never-saved → defaults(); blank text field → its default; a SAVED EMPTY list hides that block (never-saved list shows defaults). "Reset to defaults" stores null.
Card URLs are guarded twice: the form rule AND resolve() drop anything not matching SAFE_URL_PATTERN (^(https?://|/)) — keep both. Add a new field to defaults() (with its type) and the page form together, or it is either uneditable or never rendered.
Not editable here by design: the form's rate/amount headline and amount ranges (from each LoanProduct).
