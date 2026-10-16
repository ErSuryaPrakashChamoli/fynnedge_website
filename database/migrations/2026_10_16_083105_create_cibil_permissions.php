<?php

use App\Modules\Cibil\Services\CibilRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates the CIBIL Settings permission on existing installs, where deploys
     * run migrations but not RoleSeeder. Additive and idempotent.
     */
    public function up(): void
    {
        CibilRoles::grant();
    }

    /**
     * The permission may have been granted to roles since; removing it on
     * rollback could lock a team out, so it is left in place.
     */
    public function down(): void {}
};
