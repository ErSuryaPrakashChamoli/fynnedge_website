<?php

namespace App\Modules\Experian\Services;

use App\Modules\CreditBureau\Services\CrifRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what with Experian. Shared by RoleSeeder (fresh installs, tests)
 * and the permissions migration (existing installs — deploys run migrations,
 * not seeders), and strictly additive: it never revokes anything an admin has
 * granted in Access Control → Roles.
 *
 *  - Credit Officer (the same starter role CRIF uses): view requests and full
 *    reports, request a score, report or PDF (reusing a recent one when it exists),
 *    retry a failed attempt Surepass never processed, download the PDF.
 *  - Deliberately granted to NO starter role: a fresh pull over a reusable
 *    result (a second charge), reconciling uncertain attempts, the raw provider
 *    JSON, and the Experian settings. super_admin has them through Shield's
 *    Gate::before bypass; give them to a team from Access Control → Roles.
 */
final class ExperianRoles
{
    /**
     * @var list<string>
     */
    public const OFFICER = [
        'ViewAny:ExperianCreditRequest',
        'View:ExperianCreditRequest',
        'ViewReport:ExperianCreditRequest',
        'Create:ExperianCreditRequest',
        'DownloadPdf:ExperianCreditRequest',
        'View:ExperianDashboard',
    ];

    /**
     * @var list<string>
     */
    public const PRIVILEGED = [
        'FetchFresh:ExperianCreditRequest',
        'Reconcile:ExperianCreditRequest',
        'ViewRaw:ExperianCreditRequest',
        'View:ExperianSettings',
    ];

    public static function grant(): void
    {
        $officer = collect(self::OFFICER)->map(fn (string $name) => Permission::findOrCreate($name));
        collect(self::PRIVILEGED)->each(fn (string $name) => Permission::findOrCreate($name));

        Role::findOrCreate(CrifRoles::OFFICER_ROLE)->givePermissionTo($officer);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
