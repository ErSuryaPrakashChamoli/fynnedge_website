# 33 — Production smoke-test checklist

**For:** the person deploying (`31-…` §6) and, for part C, the Head of Advisory at go-live.

Record each result as Pass / Fail plus initials. **Any failure in part B means abort** (`31-…` §8).

| Part | Where | When |
| --- | --- | --- |
| A | **Staging** host, on the release image | Before deployment |
| B | **Production** with the service **OFF** | Immediately after deployment |
| C | Production, controlled go-live | After gate G (`34-…`) |

## A. Staging: the image itself (proves INF-02)

| # | Check | Expected | Result |
| --- | --- | --- | --- |
| A-01 | `php -v` in the container | The PHP version that satisfies `vendor/composer/platform_check.php` (≥ 8.4.1) | |
| A-02 | `/` loads | 200; no "Composer detected issues in your platform" | |
| A-03 | `php artisan about` | Environment production, debug off, cache drivers as configured | |
| A-04 | Container log during start (INF-01) | A normal start with migrations pending **refuses** and lists them. The one-off start with `RUN_MIGRATIONS=true` shows 8 `DONE` lines. Then a normal start runs config, route and view cache with no errors. | |
| A-05 | Parts B-01 to B-14 against staging | All pass | |
| A-06 | `nginx -t`; `curl -H "Host: fynnedge.com" http://<staging>/up` and `curl -H "Host: example.com" http://<staging>/up` | Syntax OK; the first gives 200; the second gets no response (connection closed) | |

## B. Production, service OFF

| # | Check | Expected | Result |
| --- | --- | --- | --- |
| B-01 | `/`, one loan page, `/calculators`, `/contact`, `/faqs` | 200; normal layout | |
| B-02 | Header, mobile menu, footer, homepage | **No** Debt Consolidation strip, menu item, footer link or homepage feature | |
| B-03 | `APP_DEBUG` | A 404 page shows no stack trace | |
| B-04 | `/debt-consolidation`, `/personal-debt-consolidation`, `/business-debt-consolidation`, `/debt-consolidation/calculator`, `/debt-consolidation/assessment`, `/debt-consolidation/faqs`, `/debt-consolidation/how-it-works` | 404 each | |
| B-05 | `/calculators/debt-consolidation` | 404; not listed on `/calculators` or in the Calculators menu | |
| B-06 | `/sitemap.xml` | No `debt-consolidation` URLs | |
| B-07 | `/robots.txt` | Same as before the deployment, plus nothing DC-specific | |
| B-08 | Admin → Debt Consolidation dashboard, cases, lender policies, scoring, settings | Every screen opens (super admin) | |
| B-09 | Administration → Roles → *Debt Advisor* → save without changes | Permissions unchanged (D-01) | |
| B-10 | General Pages → the DC hub → *Preview* | Renders through the signed preview, with forms hidden | |
| B-11 | Existing journeys: credit score check (OTP arrives), a loan enquiry, the EMI calculator | Work as before | |
| B-12 | Queue | `php artisan queue:work --once` processes a test job; `failed_jobs` has no new rows | |
| B-13 | Scheduler | `schedule:list` shows `debt-consolidation:enforce-retention` at 03:00 | |
| B-14 | Logs for 30 minutes after the deployment | No new error types | |

## C. Go-live (service ON), controlled window

Use a staff member's own phone number and email. Withdraw the test case at the end.

| # | Check | Expected | Result |
| --- | --- | --- | --- |
| C-01 | Switch the service ON; publish the approved pages | Strip, menu, footer and homepage feature appear; pages 200; sitemap lists them | |
| C-02 | `/calculators/debt-consolidation` | 200; listed on `/calculators`; default figures as in `23-…` §3.2 | |
| C-03 | Submit an enquiry from the hub (OTP) | OTP arrives; enquiry in the lead inbox; customer email and team alert arrive; **links in both emails start with the production address** | |
| C-04 | Add 2 loans, submit the assessment | Case status *Assessment in progress*; assessment computed | |
| C-05 | Portal | Case visible; another test account cannot see it (404) | |
| C-06 | Staff upload of a PDF on the test case; then download | Stored encrypted; download works; event in the timeline | |
| C-07 | Generate and release a customer report | Visible in the portal; the PDF opens | |
| C-08 | Withdraw the test case | Status *Withdrawn*; the customer is notified | |
| C-09 | Switch-off drill: untick "live", check B-04 and B-05, tick it again | Instant | |
