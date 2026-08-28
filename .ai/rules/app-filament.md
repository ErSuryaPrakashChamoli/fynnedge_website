---
paths:
  - 'app/Filament/**/FileUpload*,config/filesystems.php'
---

# App Filament

## Fresh environments need `php artisan storage:link` — Filament FileUpload defaults to the public disk
Lender logo (LenderForm) and SEO social image (SeoFormSection) both use Filament's `FileUpload` with no explicit `->disk(...)`, so they land on the `public` disk (storage/app/public) and are served at `/storage/...`, which only resolves through the `public/storage` symlink. That symlink is gitignored (Laravel default) and was missing in this dev environment until Phase 15 — any upload before that point 404'd silently on the public site. Any fresh clone/deploy must run `php artisan storage:link` once; verified live (2026-08-28) via a file placed in storage/app/public and fetched over HTTP after linking.

Note: no public blade view currently renders `lender.logo_path` — it's only shown in the admin ImageColumn. That's a content gap, not a bug, if/when lender logos need to appear on the public site.
