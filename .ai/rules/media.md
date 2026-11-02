---
paths:
  - app/Support/Media/ResponsiveImage.php
---

# Media

## ResponsiveImage::generate() must never throw into a save
On 2026-11-02 a production banner image change returned 500 because imagescale() returned false and imagealphablending(false) threw a TypeError inside HasResponsiveImage's saved hook. Everything after imagecreatefromstring() is now inside one try/catch that calls report() and keeps the copies already written. resize() works out the height itself as max(1, intdiv(...)), because imagescale's -1 gives 0 for a very wide, short image and throws a ValueError on PHP 8.5. When imagescale() returns false, resize() falls back to imagecopyresampled(). Keep any new GD call inside that try.
