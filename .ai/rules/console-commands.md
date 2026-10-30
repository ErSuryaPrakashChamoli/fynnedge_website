---
paths:
  - 'app/Support/Media/ResponsiveImage.php,app/Models/Banner.php,app/Console/Commands/GenerateResponsiveImages.php'
---

# Console Commands

## Banner WebP srcset copies live under responsive/, made on save and by media:responsive-images
ResponsiveImage writes WebP copies (480/768/1080/1440, q80, never upscaled; the first width past the upload holds it at its own size) to responsive/{dir}/{name}-{width}.webp on the upload's disk. Banner::booted() makes them on create or image change and deletes the old ones unless another banner shares the path. The carousel's <source> lists only copies that exist, so a banner with no copies just serves the upload. Never register responsive/ in MediaRegistry, or Media Governance offers every copy for deletion as unused. Images uploaded before 2026-10-29 need `php artisan media:responsive-images` once (add --force to rewrite). It needs GD with WebP and does nothing without it.
