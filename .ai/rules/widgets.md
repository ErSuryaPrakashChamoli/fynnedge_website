---
paths:
  - 'resources/css/filament/admin/theme.css,resources/views/filament/**,resources/views/components/admin/**,app/Filament/Widgets/**'
---

# Widgets

## Admin design system: no card grids, stat bands, module accents
User decision (2026-11-24): the admin must not use grids of separate stat cards. Key figures use <x-admin.stat-band :items="[...]"> (resources/views/components/admin/stat-band.blade.php: label, value, description, tone, url, badge, hint); notices use <x-filament::callout>; titled panels use <x-filament::section>. Dashboard widgets are custom Widget views (pulse band, feed, ring + status rows, live/draft bars, timeline), not StatsOverviewWidget/TableWidget. Each sidebar module has an accent colour keyed by `data-module="{cluster slug}"` (set in AdminModuleCluster::getNavigationItems and HelpCenter) and defined in theme.css — a new module needs a [data-module='slug'] block there. Sidebar groups are non-collapsible (no arrows); module tab groups that open a menu get a chevron from theme.css. Run `npm run build` after admin view/CSS changes.
