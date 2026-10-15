<?php

use App\Modules\CreditBureau\Services\CrifRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Gives the Credit Officer role the customer permissions it needs to add a
     * customer and open the customer page where "Fetch CRIF Report" lives, on
     * installs that already ran the original CRIF permissions migration.
     * Additive and idempotent, like CrifRoles::grant() itself.
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
