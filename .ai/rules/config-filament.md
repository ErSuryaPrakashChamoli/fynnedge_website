---
paths:
  - 'config/filesystems.php,app/Filament/**/*Form.php,docker/entrypoint.sh'
---

# Config Filament

## The public disk must keep throw => true — a failed upload otherwise saves a dangling path
The 'public' disk in config/filesystems.php sets 'throw' => true (changed 2026-09-12); do not revert it.

With throw => false, a write that fails on the server (storage/app/public not writable by php-fpm, or a path masked by a container volume mount) was swallowed and returned false, but Filament's FileUpload still returned the generated path it had already computed. Result: the record saved with e.g. image_path = 'banners/01M2A38...jpg' pointing at a file that was never written, no form error, and the admin saw a successful save. Reproduced in tests/Feature/Filament/BannerImageUploadTest.php ("fails loudly instead of saving an imageless banner").

Diagnosing this from the admin UI: Filament's ImageColumn calls $disk->exists() and renders nothing when false, so an EMPTY image cell means the file is missing from the disk. A broken-image icon instead means the file exists but the URL is wrong (APP_URL / missing public/storage symlink). Different bugs — read the cell before guessing.

Reads on the public disk are all exists()-guarded (MediaCatalog, MediaGovernance) so throw => true does not affect them. docker/entrypoint.sh re-creates storage/app/{public,private} and re-chowns storage to www-data on every boot, because the Dockerfile's build-time chown is masked by the storage_data volume mounted at /var/www/html/storage/app.
