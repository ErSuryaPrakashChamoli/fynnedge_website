---
paths:
  - 'app/Filament/Clusters/**,app/Filament/Resources/**,app/Filament/Pages/**,app/Support/Admin/**'
---

# Admin

## Admin modules = clusters declared in AdminModules; URLs never change
User decision (2026-11-24): sidebar shows main modules only (Filament clusters), each module's screens are top tabs. Membership/group/sort/label live in ONE map, App\Filament\Clusters\AdminModules::placements(); a screen joins by `use BelongsToAdminModule` (no $navigationGroup on the class). AdminModuleCluster overrides prependClusterSlug/prependClusterRouteBaseName so URLs and route names stay exactly as before clustering (/admin/faqs, filament.admin.resources.faqs.*) — never remove those overrides; only /admin/{module} landing routes are new. A new screen must get a row in AdminModules AND an entry in App\Support\Admin\AdminGuide::screens() (help text, dependencies via AdminRequirements) — AdminNavigationTest/AdminHelpTest fail otherwise. Shield's RoleResource joins Administration via config filament-shield.shield_resource.cluster + FilamentShieldPlugin->navigationGroup(null). theme.css flattens .fi-page-main (display:contents) to put the tabs above the title.
