<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * The Media Library page isn't a Filament Resource, so Shield never auto-generates
     * a permission for it the way it does for a Resource's CRUD verbs (which RoleSeeder
     * creates on demand via findOrCreate). This creates that permission explicitly, the
     * same way 2026_08_30_125950_create_view_audit_log_permission.php does for the
     * Audit History tabs — nobody has it by default except super_admin (via Shield's
     * Gate::before bypass); an admin grants it to a role deliberately.
     */
    public function up(): void
    {
        Permission::findOrCreate('View:MediaGovernance');
    }

    public function down(): void
    {
        Permission::findByName('View:MediaGovernance')?->delete();
    }
};
