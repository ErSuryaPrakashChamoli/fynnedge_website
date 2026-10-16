<?php

namespace App\Modules\Equifax\Services;

use App\Modules\CreditBureau\Services\CrifRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what with Equifax. Shared by RoleSeeder (fresh installs, tests)
 * and the permissions migration (existing installs — deploys run migrations,
 * not seeders), and strictly additive: it never revokes anything an admin has
 * granted in Access Control → Roles.
 *
 *  - Credit Officer (the same starter role CRIF uses): view requests and full
 *    reports, request a score or report (reusing a recent one when it exists),
 *    retry a failed attempt Surepass never processed, download the PDF.
 *  - Deliberately granted to NO starter role: a fresh pull over a reusable
 *    result (a second charge), reconciling uncertain attempts, the raw provider
 *    JSON, and the Equifax settings. super_admin has them through Shield's
 *    Gate::before bypass; give them to a team from Access Control → Roles.
 */
final class EquifaxRoles
{
    /**
     * @var list<string>
     */
    public const OFFICER = [
        'ViewAny:EquifaxCreditRequest',
        'View:EquifaxCreditRequest',
        'ViewReport:EquifaxCreditRequest',
        'Create:EquifaxCreditRequest',
        'DownloadPdf:EquifaxCreditRequest',
        'View:EquifaxDashboard',
    ];

    /**
     * @var list<string>
     */
    public const PRIVILEGED = [
        'FetchFresh:EquifaxCreditRequest',
        'Reconcile:EquifaxCreditRequest',
        'ViewRaw:EquifaxCreditRequest',
        'View:EquifaxSettings',
    ];

    public static function grant(): void
    {
        $officer = collect(self::OFFICER)->map(fn (string $name) => Permission::findOrCreate($name));
        collect(self::PRIVILEGED)->each(fn (string $name) => Permission::findOrCreate($name));

        Role::findOrCreate(CrifRoles::OFFICER_ROLE)->givePermissionTo($officer);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
