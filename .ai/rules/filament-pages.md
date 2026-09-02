---
paths:
  - 'app/Support/Media/**,app/Filament/Pages/MediaGovernance.php'
---

# Filament Pages

## Media Governance is a computed catalog, not a media table
Do NOT create a media/asset Eloquent table. `App\Support\Media\MediaCatalog::all()` computes a read-only catalog live from `MediaRegistry::all()` (the curated list of genuine `*_path` model/Setting fields — add a new image field there, not via column scanning) cross-referenced against `Storage::disk($disk)->allFiles($directory)`. A file's status is `used` (referenced + exists), `unused` (exists, zero references — safe to delete), or `missing` (referenced but file absent — never auto-remove the DB reference). `MediaGovernance::deleteUnused()` re-runs the catalog and re-checks status immediately before deleting, to close the race window between page load and the delete click — never trust a status computed before the request. Discovered but NOT fixed (out of scope, "existing SEO architecture" is off-limits): `SeoFormSection`'s `og_image_path` FileUpload omits `->disk('public')`, so with `FILESYSTEM_DISK=local` every OG image upload silently lands on the private disk and reads back as literally missing — Media Governance will correctly show these as "Missing"; that's real signal, not a bug in the catalog. See `.ai/rules/app-filament.md` for the same disk-omission trap already documented once for `LenderForm`.
