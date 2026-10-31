---
paths:
  - '.env*,config/app.php'
---

# General

## Production runs with APP_DEBUG=true; check it before trusting any performance number
Found 2026-10-31: https://fynnedge.com serves Livewire's unminified livewire.js (590 KB), which Livewire returns only when config('app.debug') is true, so production's .env has APP_DEBUG=true. That exposes stack traces and environment details on any exception. Fix on the server: APP_DEBUG=false, then php artisan config:cache. Quick external check: curl the livewire.js URL from a calculator page; ~254 KB means debug is off, ~590 KB means on.
