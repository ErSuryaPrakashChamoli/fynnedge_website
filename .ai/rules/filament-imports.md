---
paths:
  - 'app/Filament/Imports/**'
---

# Filament Imports

## Rate upload must find the offer across same-named lenders and loan products
Live data differs from local: the personal loan product is "Personal Loans" (slug personal-loans), there can be other products of the same loan type, and more than one lender record can share a name. LenderProductRateImporter::resolveOffer() therefore collects every lender id matching the name/slug and every loan product matching the slug/name or its category, and picks the offer that exists (named product first, active first). Picking one lender and one product first made every row fail on live with "X has no Personal Loans offer". Eloquent Collection::map on an empty collection stays an Eloquent Collection — call toBase() before pushing strings.
