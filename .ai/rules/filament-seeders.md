---
paths:
  - 'app/Models/User.php,app/Filament/**,config/filament-shield.php,config/permission.php,database/seeders/RoleSeeder.php'
---

# Filament Seeders

## Panel access is two-layered: is_admin gates login, Shield roles gate modules
Admin-panel access control is `spatie/laravel-permission` + `bezhansalleh/filament-shield` (v4.x, supports Filament ^5.7), NOT a hand-rolled system.

- `users.is_admin` (checked in User::canAccessPanel()) only answers "can this account log into /admin at all" — it is unchanged from before Shield existed. It does NOT mean full access.
- What a logged-in user can see/do is entirely driven by their Spatie role(s) via Shield-generated policies (one per Filament resource model, in app/Policies or a sibling Policies/ dir next to non-app/Models model dirs — see .ai/rules or filament-shield's README "Skipping Provided Policies" section for the placement rule). Never write manual authorize()/can() checks in a Resource for this — regenerate/edit the policy instead (`php artisan shield:generate --resource=XResource --panel=admin`).
- `super_admin` is a real Spatie role (config/filament-shield.php: `super_admin.define_via_gate = true`), wired to a Gate::before bypass — NOT a permission-per-resource list. Any user with that role skips every policy check regardless of what permissions exist.
- [[super-admin-migration-safety]]: a data migration (`2026_08_30_115301_assign_super_admin_role_to_existing_admins.php`) granted `super_admin` to every pre-existing `is_admin=true` user so the rollout didn't lock anyone out. Any future "give this admin full access" need should assign that same role, not touch `is_admin`.
- `database/factories/UserFactory.php`'s `configure()` auto-assigns `super_admin` to any factory-created user with `is_admin: true` — this is a **test-only** convenience so the ~400 pre-existing `User::factory()->create(['is_admin' => true])` call-sites keep full access without editing them all. To test a *restricted* role in a new test, create the user normally then call `$user->syncRoles(['Marketing'])` (or `'SEO'`) afterward — `syncRoles` replaces the auto-granted `super_admin`, it doesn't add to it.
- Starter roles ("Marketing", "SEO") are seeded by `database/seeders/RoleSeeder.php` (called from DatabaseSeeder) as an editable example, not a fixed taxonomy — permission names follow Shield's `{Method}:{Model}` convention (e.g. `Update:Article`), grouped per-module (resource), not per-field. Real role management happens in the panel itself, under Access Control → Roles (Shield's bundled resource at `/admin/shield/roles`) and → Users (`app/Filament/Resources/Users/UserResource.php`, new — lets an admin create a user and assign roles via the `roles` relationship Select).
- Adding a brand-new Filament Resource still requires one manual step: `php artisan shield:generate --resource=YourResource --panel=admin` to generate its permissions + policy before any role can be granted access to it.
