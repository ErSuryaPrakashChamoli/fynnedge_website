---
paths:
  - 'app/Support/Media/ResponsiveImage.php,app/Models/Concerns/HasResponsiveImage.php,resources/views/components/site/banner-carousel.blade.php'
---

# Concerns Views Components Site

## Homepage LCP regressed? First check the first banner has its WebP copies
Found 2026-11-24: mobile LCP 5.1s because the first banner (uploaded 2026-11-17) had no responsive/ copies, so the carousel served the 583KB upload instead of a ~77KB 768w copy. Every banner copy on production has Last-Modified 2026-11-03 09:48 GMT, i.e. one CLI `media:responsive-images` run; the admin save hook has not produced a copy there. Until the server cause (web-user write permission on storage/app/public/responsive, or PHP-FPM's GD lacking WebP; see laravel.log at the upload time) is fixed, every new banner/product/article image needs `php artisan media:responsive-images` run on the server after upload. Quick external check: curl -sI https://fynnedge.com/storage/responsive/banners/{name}-768.webp must be 200 (a missing storage file returns Laravel's 403 page).
