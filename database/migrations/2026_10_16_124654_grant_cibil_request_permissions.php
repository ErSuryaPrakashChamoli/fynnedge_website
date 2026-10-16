<?php

use App\Modules\Cibil\Services\CibilRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates the CIBIL request permissions (and, if missing, CIBIL Settings)
     * and gives the Credit Officer role its everyday set, on existing installs
     * where deploys run migrations but not RoleSeeder. Additive and idempotent:
     * nothing an admin granted is revoked.
     */
    public function up(): void
    {
        CibilRoles::grant();
    }

    /**
     * Roles and permissions may have been assigned to people since; removing
     * them on rollback could lock a team out, so they are left in place.
     */
    public function down(): void {}
};
