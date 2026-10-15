<?php

namespace App\Modules\CreditBureau\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what with CRIF credit reports. Shared by RoleSeeder (fresh
 * installs, tests) and the permissions migration (existing installs — deploys
 * run migrations, not seeders), and strictly additive: it never revokes
 * anything an admin has granted in Access Control → Roles.
 *
 *  - Credit Officer: view reports, request a report (reusing a recent one when
 *    it exists), retry a failed attempt Surepass never processed, and download
 *    the PDF.
 *  - Deliberately granted to NO starter role: fetching a fresh report over a
 *    reusable one (a second charge), reconciling uncertain attempts, viewing the
 *    raw provider JSON, and the CRIF settings page. super_admin has them through
 *    Shield's Gate::before bypass; give them to a team from Access Control → Roles.
 */
final class CrifRoles
{
    public const OFFICER_ROLE = 'Credit Officer';

    /**
     * @var list<string>
     */
    public const OFFICER = [
        'ViewAny:CrifReport',
        'View:CrifReport',
        'Create:CrifReport',
        'DownloadPdf:CrifReport',
        'View:CrifDashboard',
    ];

    /**
     * @var list<string>
     */
    public const PRIVILEGED = [
        'FetchFresh:CrifReport',
        'Reconcile:CrifReport',
        'ViewRaw:CrifReport',
        'View:CrifSettings',
    ];

    public static function grant(): void
    {
        $officer = collect(self::OFFICER)->map(fn (string $name) => Permission::findOrCreate($name));
        collect(self::PRIVILEGED)->each(fn (string $name) => Permission::findOrCreate($name));

        Role::findOrCreate(self::OFFICER_ROLE)->givePermissionTo($officer);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
