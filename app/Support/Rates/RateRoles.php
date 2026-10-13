<?php

namespace App\Support\Rates;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what with interest rates. Shared by RoleSeeder (fresh installs,
 * tests) and the permissions migration (existing installs — deploys run
 * migrations, not seeders), and strictly additive: it never revokes anything
 * an admin has granted in Access Control → Roles.
 *
 *  - Marketing (editor): create and edit drafts, record evidence, submit for approval.
 *  - Marketing Publisher: approve, publish, send back and withdraw rates. Give it
 *    to a marketing manager alongside their Marketing role.
 *  - Rate Reviewer: read-only access to rates, evidence and version history.
 *  - super_admin: everything, through Shield's Gate::before bypass.
 */
final class RateRoles
{
    public const EDITOR_ROLE = 'Marketing';

    public const PUBLISHER_ROLE = 'Marketing Publisher';

    public const REVIEWER_ROLE = 'Rate Reviewer';

    /**
     * @var list<string>
     */
    public const READ = ['ViewAny:LenderProductRate', 'View:LenderProductRate'];

    /**
     * @var list<string>
     */
    public const EDIT = ['Create:LenderProductRate', 'Update:LenderProductRate', 'Delete:LenderProductRate', 'Submit:LenderProductRate'];

    public const PUBLISH = 'Publish:LenderProductRate';

    public static function grant(): void
    {
        $read = collect(self::READ)->map(fn (string $name) => Permission::findOrCreate($name));
        $edit = collect(self::EDIT)->map(fn (string $name) => Permission::findOrCreate($name));
        $publish = Permission::findOrCreate(self::PUBLISH);

        Role::findOrCreate(self::EDITOR_ROLE)->givePermissionTo([...$read, ...$edit]);
        Role::findOrCreate(self::PUBLISHER_ROLE)->givePermissionTo([...$read, ...$edit, $publish]);
        Role::findOrCreate(self::REVIEWER_ROLE)->givePermissionTo($read);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
