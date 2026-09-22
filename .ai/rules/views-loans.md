---
paths:
  - 'resources/views/components/site/lender-comparison-table.blade.php,resources/views/loans/**'
---

# Views Loans

## Lender offers render only as the lender comparison chart
x-site.lender-comparison-table is the single lender listing on loans/show, loans/landing-page (by amount/type/need) and show-flexi-hybrid. The old lender-offer-card grid was removed on 2026-09-22, so don't re-add cards. The chart puts lenders in rows and terms in columns, sorted lowest rate first on the server. Alpine handles sorting, the Bank/NBFC filter and "tick up to 4 → Compare selected". The row itself is NOT clickable (removed at the user's request); only the lender name link and the Apply Now button go to route('loans.apply'). Rows are always server-rendered, and Alpine only reorders and hides them. The badges (Lowest rate, Highest amount, Longest tenure) are awarded only when at least 2 offers have that figure. Sort chip labels deliberately differ from the badge text so tests can assertDontSee the badges.
