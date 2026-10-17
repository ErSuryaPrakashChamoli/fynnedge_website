<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crediting or debiting a customer's wallet by hand moves money, so it has its
 * own permission, granted to no starter role (super_admin has it through
 * Shield's bypass; give it to a team in Access Control → Roles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('AdjustWallet:Customer');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'AdjustWallet:Customer')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
