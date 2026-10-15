<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const PERMISSIONS = ['ViewAny:SocialVideo', 'View:SocialVideo', 'Create:SocialVideo', 'Update:SocialVideo', 'Delete:SocialVideo'];

    /**
     * Deploys run migrations but not RoleSeeder, so an existing Marketing role
     * would otherwise never see Content → Social media videos. Additive only:
     * whatever else an admin has granted or revoked on the role is kept.
     */
    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)->map(fn (string $name): Permission => Permission::findOrCreate($name));

        Role::query()->where('name', 'Marketing')->first()?->givePermissionTo($permissions);
    }

    public function down(): void
    {
        Role::query()->where('name', 'Marketing')->first()?->revokePermissionTo(self::PERMISSIONS);
    }
};
