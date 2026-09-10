---
paths:
  - 'app/Support/Seo/**,resources/views/components/layouts/app.blade.php,public/robots.txt,resources/views/sitemap.blade.php'
---

# Layouts Views

## One JSON-LD @graph with stable @ids; loan pages are Service, never FinancialProduct
`App\Support\Seo\SchemaGraph::build()` renders ONE `@graph` per page from the layout: Organization (from `OrganizationSchema`, Settings-driven) + WebSite + WebPage, cross-referenced by stable `@id`:
  {site}/#organization · {site}/#website · {canonical}#webpage · {canonical}#service · {current}#faq · {current}#breadcrumb
Controllers append page-specific nodes via a `schemaNodes` view variable → `:schema-nodes` on the layout (see LoanProductController/LoanLandingPageController). Never emit a second Organization — reference `SchemaGraph::organizationId()` instead. BreadcrumbList/FAQPage/Article stay in their own script blocks beside the markup they describe; crawlers merge blocks, and they carry `@id` so they're addressable.

FynnEdge is a DSA/advisory, NOT a lender. A loan page is `@type: Service` with `provider → #organization` and `serviceType` — never `FinancialProduct`, and never `interestRate`/`annualPercentageRate`/fees/amount/tenure/offers, which belong to the individual lender and vary per applicant. The page microdata (`itemtype="https://schema.org/Service"`) must stay consistent with this. EntityGraphTest pins both rules.

`Sitemap::entries()` builds /sitemap.xml from the same `published()` scopes the public controllers use, plus `CalculatorCatalog` for calculator URLs; `Sitemap::ROUTED_PAGE_SLUGS` is the single source shared with routes/web.php's legal-page loop. robots.txt is NO LONGER a static public/ file — it is a route (RobotsController) that follows the sitewide indexing setting and advertises route('sitemap'); see .ai/rules/nginx.md.

Page-level knobs on the layout: `page-type` (AboutPage/ContactPage/CollectionPage, default WebPage) and `og-type` (default website, `article` on articles).
