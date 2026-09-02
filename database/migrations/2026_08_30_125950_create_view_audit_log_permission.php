<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * The History/audit tabs aren't their own Filament Resource, so Shield never
     * auto-generates a permission for them the way it does for every real Resource.
     * This creates that permission explicitly, following the same "{Method}:{Model}"
     * naming Shield uses elsewhere (see RoleSeeder), so it's assignable to any role
     * from Access Control → Roles like any other permission. Nobody has it by
     * default except super_admin (via Shield's Gate::before bypass) — an admin
     * grants it to a role deliberately when that team should see change history.
     */
    public function up(): void
    {
        Permission::findOrCreate('View:AuditLog');
    }

    public function down(): void
    {
        Permission::findByName('View:AuditLog')?->delete();
    }
};
