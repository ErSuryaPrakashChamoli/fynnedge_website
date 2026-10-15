---
paths:
  - resources/views/components/site/nav-link.blade.php
  - resources/views/components/site/banner-carousel.blade.php
  - resources/views/components/site/header.blade.php
  - resources/views/components/site/testimonials.blade.php
  - resources/views/components/site/social-videos.blade.php
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

## Written testimonials are a scroll-snap row with arrows, not a grid
x-site.testimonials renders ALL published testimonials as one horizontal snap-scroll row (`data-testimonial-card`, 1 card at ~85% on phones, 2 at sm, 3 at lg) with previous/next arrow buttons that Alpine hides when the row already fits (same atStart/atEnd pattern as x-site.video-testimonials). Never cap the query at 3 or switch back to a wrapping grid: the user asked (2026-09-17) for more than three testimonials with left/right arrows. Pinned by tests/Feature/TestimonialCarouselTest.php.

## Credit Score header menu is a champagne pill with a "Free" badge
The user asked on 2026-10-14 for the Credit Score header item to stand out and grab attention: bold, golden champagne, with "Free" before it. On desktop it is `<x-site.nav-dropdown ... featured badge="Free">`. The mobile menu's Credit Score button copies the same markup by hand. `.nav-champagne` (app.css) is the pill: champagne gradient, gold hairline, glow, and a light sweep that reuses the promo-bar-sweep keyframe and is switched off under prefers-reduced-motion. `.nav-champagne-badge` stays gold-on-navy in both themes. The pill reads only the --color-champagne, -strong, -soft and -shine tokens, which are defined in @theme and in both dark-mode blocks. Light-mode pill text is #6f521d, which keeps AA contrast. Pale champagne such as #e6c98f fails as text on the light header, so it is used only in dark mode.
Width trap: the 1024–1279px header is full. The pill made it overflow by 62px, so the Primary nav uses `gap-3.5 xl:gap-8` and the pill uses `px-2.5 xl:px-3`. Widening the pill or adding a nav item means measuring header overflow (scrollWidth − clientWidth of `header > div`) at 1024px again. In nav-dropdown, keep the font weight inside each conditional branch, because a base font-medium plus font-bold gives an unpredictable result. Pinned by SiteBrandingTest.

## Social videos row auto-advances; advance() must measure() itself
No cap on how many videos render (5 visible at xl, the rest scroll). Once the row overflows it moves one card every 3.5s (card width + the track's computed column-gap) and loops back to the start, pausing while mouse-hovered, focused, touched in the last 6s, in a background tab, or under prefers-reduced-motion; arrow clicks restart the timer. advance() calls measure() first — don't rely on the @scroll listener to refresh atEnd, or it can stick at the last card instead of looping. Headless Chromium --virtual-time-budget never animates behavior:'smooth' scrolls, so to verify the loop there, force behavior:'instant' in a probe script.
