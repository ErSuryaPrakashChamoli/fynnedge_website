---
paths:
  - resources/views/components/site/nav-link.blade.php
---

# Site

## nav-link silently degrades to "Coming soon" for a missing named route
x-site.nav-link wraps its link in @if (Route::has($route)), falling back to a disabled grey span titled "Coming soon" when the route doesn't exist. This is by design (lets nav links be added ahead of the feature), but it means a typo'd or never-wired route name fails silently instead of erroring — the header still renders fine, so it's easy to miss. The "Resources" nav item referenced route('resources.index') from Phase 2 onward with no route ever defined until Phase 16 added App\Models\Article + /resources routes. When a nav link looks dead/greyed-out on the public site, check Route::has() for its route name before assuming it's a styling bug.
