---
paths:
  - 'app/Support/Rates/**'
---

# Support Rates

## Third-party (comparison-site) rates are labelled, never ranked
lender_product_rates.source_type is official (default) or third_party; third_party requires source_name (RatePublicationRules). Only use third_party when the lender's own site can't be read. RateBook keeps them out of headline() (product "from X%", tokens, calculator presets) and the comparison table's "Lowest rate" badge; anything that ranks lenders or computes with a rate (JourneyController sort/recommendation, flexi-hybrid calculator) must call RateBook::officialQuoteFor(), not quoteFor(). Public label is "As listed on {source_name}, {date} · confirm with {lender}", never "Rate verified". They stay in needsVerification()/Needs attention until re-checked; the Verify action and an upload row with blank source_type make the rate official again.
