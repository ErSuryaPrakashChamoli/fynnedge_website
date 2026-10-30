---
paths:
  - 'app/Support/Media/ResponsiveImage.php,app/Filament/Pages/Settings.php,app/Http/Controllers/AboutController.php'
---

# Pages Http Controllers

## The founder photo uses the same WebP srcset copies as banners
The About page's founder photo has a 280px box, but the upload was a 1.8MB PNG. Measured 2026-10-30, that download made mobile lab LCP swing between 0.8s and 10.7s. Settings::save() calls refreshFounderPhotoCopies(): it deletes the old photo's copies and makes the new one's whenever founder_photo changes. AboutController passes founderPhotoSrcset, and about.blade.php wraps the img in <picture> with sizes="280px". media:responsive-images backfills banners and the founder photo. Its output reads "Checked N image(s)". Pinned by FounderPhotoResponsiveImagesTest.
