---
paths:
  - 'app/Filament/**/FileUpload*,config/filesystems.php'
---

# App Filament

## Fresh environments need `php artisan storage:link` — Filament FileUpload defaults to the public disk
Lender logo (LenderForm) and SEO social image (SeoFormSection) both use Filament's `FileUpload` with no explicit `->disk(...)`, so they land on the `public` disk (storage/app/public) and are served at `/storage/...`, which only resolves through the `public/storage` symlink. That symlink is gitignored (Laravel default) and was missing in this dev environment until Phase 15 — any upload before that point 404'd silently on the public site. Any fresh clone/deploy must run `php artisan storage:link` once; verified live (2026-08-28) via a file placed in storage/app/public and fetched over HTTP after linking.

Note: no public blade view currently renders `lender.logo_path` — it's only shown in the admin ImageColumn. That's a content gap, not a bug, if/when lender logos need to appear on the public site.

## Lender logo_path IS now rendered on the public site (homepage trust marquee)
Update to the "content gap" note below: as of the homepage trust-marquee section (resources/views/home.blade.php), lender.logo_path is rendered publicly in four places — loan product lender-offer cards, eligibility results, the application page header, and now the homepage marquee (HomeController passes active lenders; <x-ui.lender-logo> falls back to initials when logo_path is empty). Uploading a logo via Admin → Lenders → Logo (LenderForm) is picked up automatically everywhere, no further code change needed. Still requires `php artisan storage:link` on fresh environments (public disk).

## FileUpload disk defaults to 'local' (private), not 'public' — always call ->disk('public') for public-facing uploads
Corrects the earlier note in this file: with FILESYSTEM_DISK=local (the current .env value), Filament's FileUpload with no explicit ->disk(...) writes to the 'local' disk, whose root is storage/app/private — NOT storage/app/public. The public/storage symlink only covers storage/app/public, so files land somewhere the symlink never reaches and 404 forever, regardless of storage:link.

LenderForm's logo_path FileUpload had this bug (fixed 2026-08-29 by adding ->disk('public')) — Lender::logoUrl() reads via Storage::disk('public'), so the upload disk must match. CompanyPhotoForm (photo_path) and SeoFormSection (og_image_path) use the same disk-less FileUpload::make(...)->directory(...) pattern and are exposed to the same bug if/when their disk('public') isn't set — verify each against its own read path (e.g. Storage::disk('public')->url(...)) rather than assuming.

Rule: any FileUpload backing a value read back with Storage::disk('public') must explicitly call ->disk('public'). Never rely on the framework/env default.
