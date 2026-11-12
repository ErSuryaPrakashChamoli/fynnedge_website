---
paths:
  - 'routes/web.php,app/Http/Middleware/HandleRedirects.php,app/Models/Redirect.php'
---

# Http Middleware Models

## Old URLs are redirects rows, never Route::redirect()
Never add Route::redirect()/permanentRedirect() for a legacy URL. HandleRedirects (global, runs before routing) can't see a route-level redirect, so "/old/" becomes a 2-hop chain: slash normalisation (/old/ → /old), then the route (/old → /new). Search Console lists that as "Page with redirect". Ship a redirects row in a migration instead (firstOrCreate, skip existing rows; see 2026_11_12_090432_move_about_us_redirect_to_redirects_table.php). A row matches whatever the slashes or case, so it is always one hop.
