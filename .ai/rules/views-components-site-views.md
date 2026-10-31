---
paths:
  - 'resources/views/components/site/banner-carousel.blade.php,resources/views/home.blade.php'
---

# Views Components Site Views

## Carousel slides after the first are display:none until preloadNeighbours shows them
Supersedes "loading=lazy is enough" in views-views-components-site.md. Chrome applies its lazy-load margin inside the carousel's overflow box, so slides 2-3 (1.5 MB of JPEG) downloaded alongside the LCP image (Lighthouse 2026-10-31). Every slide image except index 0 also carries `hidden`; preloadNeighbours() removes it (and sets eager) for current/next/previous on window load and on every move. Keep both attributes on slide images. Dots are 24px buttons around the visual pill (WCAG 2.5.8); the row sits at bottom-1, inside the slide text's 40px inset. The CTA appends the banner's alt/heading as sr-only text so "More info" has context. Pinned by HomepageHeroBannerTest.
