<?php

use App\Modules\CreditBureau\Services\CrifRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates the CRIF report permissions and the Credit Officer starter role on
     * existing installs, where deploys run migrations but not RoleSeeder.
     * Additive and idempotent: nothing an admin has granted is revoked.
     */
    public function up(): void
    {
        CrifRoles::grant();
    }

    /**
     * Roles and permissions may have been assigned to people since; removing
     * them on rollback could lock a team out, so they are left in place.
     */
    public function down(): void {}
};
