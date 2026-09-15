---
paths:
  - resources/views/components/site/nav-link.blade.php
  - resources/views/components/site/banner-carousel.blade.php
  - resources/views/components/site/header.blade.php
---

# Site

## nav-link silently degrades to "Coming soon" for a missing named route
x-site.nav-link wraps its link in @if (Route::has($route)), falling back to a disabled grey span titled "Coming soon" when the route doesn't exist. This is by design (lets nav links be added ahead of the feature), but it means a typo'd or never-wired route name fails silently instead of erroring — the header still renders fine, so it's easy to miss. The "Resources" nav item referenced route('resources.index') from Phase 2 onward with no route ever defined until Phase 16 added App\Models\Article + /resources routes. When a nav link looks dead/greyed-out on the public site, check Route::has() for its route name before assuming it's a styling bug.

## Banner slide text must clear the carousel controls, forcefully on phones
With 2+ banners, the arrows (vertically centred at each side), the dots and the pause button overlay the slide. `.banner-content` is therefore inset past them: `max-sm:px-12! pb-10 pt-5 sm:px-16 sm:py-10`. Arrows occupy 44px from each edge on phones (`left-2 h-9`) and 52px from sm up (`sm:left-3 sm:h-10`); the pause button reaches 38px from the bottom.
The phone side inset is !important on purpose: `Banner::contentStyle()` writes the admin's side spacing as inline `padding-left`/`padding-right` %, and a percentage of a 342px box put the heading under the arrow and a right-aligned button under the pause button. From sm up the admin's spacing still wins.
Resize a control, and you must re-check this inset. A single banner has no controls and keeps `p-6 sm:p-10`.

## Mobile menu must be its own scroll area, with the page locked while open
`#mobile-nav` hangs off the sticky header (`absolute top-full`). With a section expanded it is taller than a phone screen, so it needs all three of these:
- a capped height: the `menuHeight` inline max-height, measured from the header's bottom on open and on resize, with a `max-h-[calc(100dvh-5rem)]` fallback before Alpine runs
- `overflow-y-auto overscroll-contain`
- the page locked while open: `x-effect` toggles `overflow-hidden` on `<html>`
Remove any of them and the lower links become unreachable while scrolling moves the page behind the menu. That was measured on a 382×672 screen: page scrolled 1,500px, menu 0px.
The menu also closes on Escape, on a click outside, and on resize to lg or wider, so the page lock can never stick on desktop. Verify changes by wheel-scrolling over the open menu in an emulated phone, not only with markup tests.
