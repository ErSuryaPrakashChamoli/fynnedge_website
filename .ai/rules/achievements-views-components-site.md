---
paths:
  - 'app/Models/Achievement.php,app/Filament/Resources/Achievements/**,resources/views/components/site/hero-stats.blade.php'
---

# Achievements Views Components Site

## Achievements are text-only — never add an image field back
The homepage stat strip renders prefix + value + suffix and a caption, and has no markup for an image. The icon_path/icon_alt columns were dropped (2026_09_09_100228) because they were write-only: an admin could upload a file that could never appear. Don't reintroduce a FileUpload/ImageColumn here without first adding the rendering for it in hero-stats.blade.php.

Count is admin-chosen: items are flex-1, so any number of published rows spreads evenly. Ordering is `sort_order` (reorderable in the table), lowest first.
