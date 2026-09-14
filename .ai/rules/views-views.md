---
paths:
  - 'app/Support/Enquiries/PartnerLenders.php,app/Support/Enquiries/QuickEnquiryPageContent.php,app/Http/Controllers/PartnerLenderController.php,app/Http/Controllers/QuickEnquiryPageController.php,resources/views/partners.blade.php,resources/views/quick-enquiry.blade.php'
---

# Views Views

## Partner lender strip and /partners share one lender set
App\Support\Enquiries\PartnerLenders::all() (active lenders, logo-first then A–Z) feeds both the /quick-enquiry logo strip and the full list at /partners (partners.index, in the sitemap), so the strip's "+N more" link count always matches that page. Never query lenders separately in either controller.
Which logos the strip shows comes from the quick_enquiry_page Setting: featured_lender_ids (admin-ordered reorderable multi-select; EMPTY means automatic, not "hide" — the one exception to the saved-empty-list-hides-block contract) and lenders_limit (clamped 1..MAX_VISIBLE_LENDERS in resolve()). PartnerLenders::featured() drops picked lenders that were later deactivated. /partners reuses lenders_label as its h1 and partners_description as its intro. Logos are uploaded per lender in Catalog → Lenders.
