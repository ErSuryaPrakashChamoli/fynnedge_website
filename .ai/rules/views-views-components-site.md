---
paths:
  - 'resources/views/home.blade.php,resources/views/components/site/banner-carousel.blade.php'
---

# Views Views Components Site

## Homepage hero is the LCP: no data-reveal, one eager banner image
The first banner image (or the h1 when no banner is published) is the homepage's Largest Contentful Paint. Measured 2026-10-29: data-reveal held it at opacity 0 until app.js ran, and app.js waits on livewire.js. That added ~2s of element render delay. Never put data-reveal on anything in the hero section. In the carousel only slide 0 loads with the page (fetchpriority="high"). Every other slide and the clone is loading="lazy" AND `hidden` (display:none, since 2026-10-31: lazy alone did not hold them back inside the carousel), and preloadNeighbours() shows the slides one step either side and flips them to eager on window load and on every move. Eager-loading all 8 banners meant 7.7MB competing with the LCP image. Pinned by HomepageHeroBannerTest.
