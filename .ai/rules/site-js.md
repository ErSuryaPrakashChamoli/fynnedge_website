---
paths:
  - 'resources/views/components/site/location-map.blade.php,resources/js/app.js'
---

# Site Js

## Map iframe uses data-src, set by app.js initDeferredFrames near the screen
The Google Maps embed is ~460KB over 16 requests, and Chrome's loading="lazy" starts an iframe 1,250–2,500px early, so the footer map loaded with every short page on a phone (articles, /contact, 2026-11-03). x-site.location-map renders the iframe with data-src and NO src; initDeferredFrames() in app.js copies it to src via IntersectionObserver (rootMargin 300px). Never put src back on it. Without JS the map box stays blank; the footer card's directions link still works. Pinned by FooterMapTest.
