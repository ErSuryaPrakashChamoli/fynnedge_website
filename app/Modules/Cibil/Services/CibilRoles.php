<?php

namespace App\Modules\Cibil\Services;

use App\Modules\CreditBureau\Services\CrifRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what with CIBIL. Shared by RoleSeeder (fresh installs, tests)
 * and the permissions migrations (existing installs — deploys run migrations,
 * not seeders), and strictly additive: it never revokes anything an admin has
 * granted in Access Control → Roles.
 *
 *  - Credit Officer (the same starter role the other bureaus use): view
 *    requests and full reports, request a score, report or PDF (reusing a
 *    recent one when it exists), retry a failed attempt Surepass never
 *    processed, download the PDF.
 *  - Deliberately granted to NO starter role: a fresh pull over a reusable
 *    result (a second charge), reconciling uncertain attempts, the raw provider
 *    JSON, and CIBIL Settings (which also switches the public CIBIL page).
 *    super_admin has them through Shield's Gate::before bypass.
 */
final class CibilRoles
{
    public const SETTINGS = 'View:CibilSettings';

    /**
     * @var list<string>
     */
    public const OFFICER = [
        'ViewAny:CibilCreditRequest',
        'View:CibilCreditRequest',
        'ViewReport:CibilCreditRequest',
        'Create:CibilCreditRequest',
        'DownloadPdf:CibilCreditRequest',
        'View:CibilDashboard',
    ];

    /**
     * @var list<string>
     */
    public const PRIVILEGED = [
        'FetchFresh:CibilCreditRequest',
        'Reconcile:CibilCreditRequest',
        'ViewRaw:CibilCreditRequest',
        self::SETTINGS,
    ];

    public static function grant(): void
    {
        $officer = collect(self::OFFICER)->map(fn (string $name) => Permission::findOrCreate($name));
        collect(self::PRIVILEGED)->each(fn (string $name) => Permission::findOrCreate($name));

        Role::findOrCreate(CrifRoles::OFFICER_ROLE)->givePermissionTo($officer);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
