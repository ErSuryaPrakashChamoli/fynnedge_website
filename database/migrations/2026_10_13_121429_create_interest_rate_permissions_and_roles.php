<?php

use App\Support\Rates\RateRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates the Catalog → Interest Rates permissions and the Marketing
     * Publisher / Rate Reviewer roles on existing installs, where deploys run
     * migrations but not RoleSeeder. Additive and idempotent: nothing an admin
     * has granted is revoked.
     */
    public function up(): void
    {
        RateRoles::grant();
    }

    /**
     * Roles and permissions may have been assigned to people since; removing
     * them on rollback could lock a team out, so they are left in place.
     */
    public function down(): void {}
};
