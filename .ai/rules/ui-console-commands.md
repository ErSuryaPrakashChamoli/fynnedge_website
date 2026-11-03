---
paths:
  - 'app/Models/Concerns/HasResponsiveImage.php,app/Models/Lender.php,resources/views/components/ui/lender-logo.blade.php,app/Console/Commands/GenerateResponsiveImages.php'
---

# Ui Console Commands

## Lender logos get WebP copies (128/320) through HasResponsiveImage
Since 2026-11-03, HasResponsiveImage reads responsiveImageColumn() (default image_path) and responsiveImageWidths() (default ResponsiveImage::WIDTHS). Lender overrides them with logo_path and LENDER_LOGO_WIDTHS = [128, 320], because loan pages sent 491KB of PNG logos for 44px discs. x-ui.lender-logo and the homepage marquee wrap the img in <picture class="contents"> (display: contents keeps the img the flex item) with a WebP <source> only when imageSrcset() is non-null, and the disc img is loading="lazy". /partners still serves the upload: its img is sized by the file's natural width, and a sizes value would change how it looks. media:responsive-images backfills lender logos, and production must run it after the deploy. Pinned by LenderLogoResponsiveImagesTest.
