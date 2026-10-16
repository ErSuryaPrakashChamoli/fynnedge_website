<?php

use App\Modules\Experian\Services\ExperianRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates the Experian permissions and gives the Credit Officer role its
     * everyday set, on existing installs where deploys run migrations but not
     * RoleSeeder. Additive and idempotent: nothing an admin granted is revoked.
     */
    public function up(): void
    {
        ExperianRoles::grant();
    }

    /**
     * Roles and permissions may have been assigned to people since; removing
     * them on rollback could lock a team out, so they are left in place.
     */
    public function down(): void {}
};
