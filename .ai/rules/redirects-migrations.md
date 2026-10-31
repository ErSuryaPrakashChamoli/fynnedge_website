---
paths:
  - 'app/Models/Redirect.php,app/Http/Middleware/HandleRedirects.php,app/Filament/Resources/Redirects/**,database/migrations/**'
---

# Redirects Migrations

## 410 Gone is a redirects row; destinations use LIVE slugs
A redirects row with status_code 410 (Redirect::GONE) has destination NULL and answers 410 via abort() in HandleRedirects (which calls SetSiteLocale::apply() first: it runs before the appended locale middleware). Redirect::resolve() returns ['destination' => null, 'status_code' => 410] for a Gone row AND for any chain ending at one — guard every consumer of resolve()/activeMap() for a null destination. RedirectForm rejects chains (destination that is itself a row source), Gone destinations, unroutable paths (UrlPath::isRoutable) and non-http(s) URLs. Production slugs differ from local seeds (live /loans/personal-loans vs local personal-loan): a migration shipping rows must use the live URLs, verified with curl, and tests assert the Location + UrlPath::isRoutable, not that the page renders locally.
