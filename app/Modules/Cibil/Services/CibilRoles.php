<?php

namespace App\Modules\Cibil\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may switch the public CIBIL page between demo and real scores. Shared by
 * RoleSeeder and the permissions migration; additive, granted to no starter
 * role (super_admin has it through Shield's Gate::before bypass).
 */
final class CibilRoles
{
    public const SETTINGS = 'View:CibilSettings';

    public static function grant(): void
    {
        Permission::findOrCreate(self::SETTINGS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
