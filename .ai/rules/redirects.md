---
paths:
  - 'app/Models/Redirect.php,app/Http/Middleware/HandleRedirects.php,app/Filament/Resources/Redirects/**'
---

# Redirects

## Redirects are global middleware, not web-group, and match on a normalised path
HandleRedirects is registered with `$middleware->prepend()` in bootstrap/app.php, NOT appendToGroup('web'). Group middleware only runs after a route matches, so a web-group redirect can never fire for the 404 URLs that are the whole point of a redirects table — this was caught by a failing test, not by reading. Running globally also lets a redirect beat a live route (renamed page keeps its old slug). The Filament panel path is skipped explicitly, or a row for /admin/... would lock admins out of the screen they'd need to delete it from.

Every path goes through Redirect::normalizePath() on write (form dehydrate), on read (cached map keys) and on match, so "/A", "a/" and "/a?x=1" are one row. Uniqueness is validated against the NORMALISED value in RedirectForm — a plain unique() rule would let a shadowing row through and fail on the DB index instead. Loop prevention is two-layer: the form rejects self-referential and chain-back-to-source destinations (RedirectForm::leadsBackTo walks the active map, bounded by visited set), and the middleware refuses to serve a row that resolves to the request path.

The active set is one Cache::rememberForever array busted by saved/deleted model hooks. Hit counters are written with the query builder on purpose — model events would bust the cache the request just read.
