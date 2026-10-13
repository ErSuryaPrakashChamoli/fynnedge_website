---
paths:
  - 'app/Support/Loans/LoanMegaMenu.php,resources/views/components/site/header.blade.php,app/Models/LoanLandingPage.php,database/seeders/LoanLocationPageSeeder.php'
---

# Models Seeders

## Loans mega menu: By Location column, plain links, narrow column select
LandingPageGroup::ByLocation pages ("Personal Loan in Delhi") carry a `city` column; LoanLandingPage::shortLabel() shows just the city in the menu and product overview (full title goes in aria-label). 24 cities × 12 products = 288 links render into the hidden menu panels on EVERY page, so: the header group links are plain <a>, not x-site.nav-link (the component cost ~0.8s per render for 288 links vs ~0.1s plain, and its active check never matched these model-bound links); LoanMegaMenu eager-loads only id/loan_product_id/group/title/slug/city (not body); keep each <a> on one line (multi-line tags added ~100KB of indentation). The panel uses -left-48 so 4 columns fit under the header from xl; below xl the 4th column wraps. City pages are seeded by LoanLocationPageSeeder (separate so prod can run `php artisan db:seed --class=LoanLocationPageSeeder --force` — deploys only migrate) for every product that already has other sub-pages.
