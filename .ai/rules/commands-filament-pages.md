---
paths:
  - 'app/Support/Media/**,app/Models/Concerns/HasResponsiveImage.php,app/Console/Commands/GenerateResponsiveImages.php,app/Filament/Pages/Settings.php'
---

# Commands Filament Pages

## HasResponsiveImage keeps image_path WebP copies for Banner, LoanProduct and Article; the logo gets 64/128px copies
Banner's own hooks moved to the HasResponsiveImage trait (2026-10-31), now also on LoanProduct and Article: copies made on create/image change, removed unless another row of that table shares the path; a soft delete keeps them, a force delete removes them. Views wrap the img in <picture> with a WebP <source> only when imageSrcset() is non-null, and set width/height from imageDimensions() (getimagesize on the upload). Settings::save() refreshes founder_photo (WIDTHS) and site_logo (ResponsiveImage::LOGO_WIDTHS = 64,128) via refreshImageCopies(); siteBranding['logoSrcset'] feeds the header/footer. SVG uploads are skipped by extension (no copies, nothing reported); a broken raster is still reported. A new image model must be added to media:responsive-images, which production must run once after deploy (needs GD with WebP).
