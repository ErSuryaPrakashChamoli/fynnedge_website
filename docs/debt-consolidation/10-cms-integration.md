# 10 — CMS integration

## Pages

| URL | `pages` slug (General Pages) | Route name | Embeds |
| --- | --- | --- | --- |
| `/debt-consolidation` | `debt-consolidation` | `debt-consolidation.hub` | enquiry form |
| `/personal-debt-consolidation` | `personal-debt-consolidation` | `debt-consolidation.personal` | enquiry form |
| `/business-debt-consolidation` | `business-debt-consolidation` | `debt-consolidation.business` | enquiry form |
| `/debt-consolidation/how-it-works` | `debt-consolidation-how-it-works` | `debt-consolidation.how-it-works` | enquiry form |
| `/debt-consolidation/calculator` | `debt-consolidation-calculator` | `debt-consolidation.calculator` | calculator suite |
| `/debt-consolidation/assessment` | `debt-consolidation-assessment` | `debt-consolidation.assessment` | application form |
| `/debt-consolidation/faqs` | `debt-consolidation-faqs` | `debt-consolidation.faqs` | — |

URLs have no trailing slash (site convention); `/debt-consolidation/` answers one 301 to `/debt-consolidation`. A row's flat slug (e.g. `/debt-consolidation-faqs`) 301s to its real address, so no page exists twice.

## Who edits what

| Content | Edited in | Notes |
| --- | --- | --- |
| Title (H1), intro, main body, Hindi | Website Pages → General Pages → the page | Draft / publish / schedule / expire, signed preview ("Preview" on the editor), audit history |
| SEO title, description, canonical, robots, social image, schema type/template | Same editor, SEO section | Uses the existing `seo_metas`; Page SEO overrides also apply |
| FAQs | Same editor, FAQs tab | Starter FAQs ship as **drafts**: review, then tick them and use "Publish selected". FAQPage JSON-LD is generated automatically |
| Hero badge, button labels, trust points, steps, "who it is for", tool cards, form headings, related-pages heading, and which sections each page shows | Website Settings → Debt Consolidation Pages (English and Hindi tabs) | Same pattern as the About page settings |
| Service on/off, applicant types, calculators shown, disclaimers, consent wording | Website Settings → Debt Consolidation Settings | Admin only |

Sections a page can show: quick enquiry form, trust points, how-it-works steps, personal/business cards, debts covered (individual / business), calculator & assessment cards, related loan pages, disclaimer.

**Related loan pages** are the site's existing loan landing pages whose titles mention debt consolidation, balance transfer or refinancing (e.g. "Personal Loan for Debt Consolidation") — linked, never duplicated.

**Structured data**: Organization/WebSite/WebPage graph from the layout, a `Service` node on the hub, personal and business pages, breadcrumbs, FAQPage.

**Sitemap**: published pages are listed automatically while the service is switched on; never while it is off.

**Site-wide preference** (owner requirement, 25 Nov 2026). These show only while the service is on and the hub page is published:

| Where | What | Edited in |
| --- | --- | --- |
| Strip above the header (every page, every width) | "New · Debt Consolidation", with a short tagline from 1024 px; on phones it has its own line | Fixed wording (translated) |
| Desktop main navigation (≥ 1280 px) | "Debt Consolidation" straight after Loans, with a "New" tag. It replaces the Eligibility link, which goes to the same page as the Check Eligibility button beside it. Below 1280 px the row is full, so the strip carries it there | Fixed |
| Mobile menu | Second item, after Loans, with a "Free assessment →" shortcut when that page is published | Fixed |
| Footer | First link in the Loans column | Fixed |
| Homepage | Feature section straight under the hero: heading, paragraph, four honest points (who it is for, free with no credit report pulled, total cost not just EMI, nothing goes to a lender without consent), "What your assessment shows", and buttons to the assessment, calculator and how-it-works pages. A button only appears when its page is published | Website Pages → Homepage & sections → Marketing Sections → placement "Homepage — Debt Consolidation feature" (heading = first line, subheading = highlighted second line, description, button label/link; Hindi tab). Unpublished → built-in wording |

The existing **promo bar** (Website Pages → Homepage & sections → Promo bars) can also be pinned to pages for a time-limited Debt Consolidation campaign; nothing is seeded.

## Launch checklist (content)

1. Review and edit the seven draft pages (copy, SEO) and publish their reviewed FAQs ("Publish selected" on each page's FAQs tab); review the Debt Consolidation Pages wording.
2. Have legal approve disclaimers and consent wording (Debt Consolidation Settings).
3. Publish the pages, then switch the service on.
