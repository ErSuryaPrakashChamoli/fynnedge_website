<?php

namespace App\Modules\DebtConsolidation\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what in Debt Consolidation. Shared by RoleSeeder (fresh
 * installs, tests) and the permissions migration (deploys run migrations, not
 * seeders), and strictly additive: it never revokes anything an admin granted.
 *
 *  - Debt Advisor: work cases end to end — liabilities, documents, scenarios,
 *    assessments, lender matching, lender applications, closures, reports,
 *    notes — and read (not change) lender policies and scoring configurations.
 *  - Marketing: the Debt Consolidation page wording screen (like the other
 *    page settings screens).
 *  - Granted to NO starter role (super_admin has them through Shield's
 *    Gate::before): deleting cases or documents, creating/approving lender
 *    policies, creating/activating scoring configurations, and the service
 *    settings. Give them to a team from Administration → Roles.
 */
final class DebtConsolidationRoles
{
    public const ADVISOR_ROLE = 'Debt Advisor';

    public const MARKETING_ROLE = 'Marketing';

    private const WORK = ['ViewAny', 'View', 'Create', 'Update'];

    /**
     * @var array<string, list<string>>
     */
    private const ADVISOR_MODELS = [
        'DebtApplication' => self::WORK,
        'DebtLiability' => self::WORK,
        'DebtCollateral' => self::WORK,
        'DebtDocument' => self::WORK,
        'DebtScenario' => self::WORK,
        'DebtForeclosureQuotation' => self::WORK,
        'DebtLenderMatch' => self::WORK,
        'DebtLenderApplication' => self::WORK,
        'DebtClosure' => self::WORK,
        'DebtNote' => self::WORK,
        'DebtConsent' => ['ViewAny', 'View', 'Create'],
        'DebtCalculationSnapshot' => ['ViewAny', 'View', 'Create'],
        'DebtReport' => ['ViewAny', 'View', 'Create'],
        'DebtLenderPolicy' => ['ViewAny', 'View'],
        'DebtScoringConfig' => ['ViewAny', 'View'],
    ];

    /**
     * @var list<string>
     */
    public const ADVISOR_EXTRA = [
        'View:DebtConsolidationDashboard',
        'ViewAny:Customer',
        'View:Customer',
    ];

    /**
     * @var list<string>
     */
    public const PRIVILEGED = [
        'Delete:DebtApplication',
        'Restore:DebtApplication',
        'Delete:DebtDocument',
        'Create:DebtLenderPolicy',
        'Update:DebtLenderPolicy',
        'Delete:DebtLenderPolicy',
        'Approve:DebtLenderPolicy',
        'Create:DebtScoringConfig',
        'Update:DebtScoringConfig',
        'Activate:DebtScoringConfig',
        'View:DebtConsolidationSettings',
    ];

    /**
     * @return list<string>
     */
    public static function advisorPermissions(): array
    {
        $permissions = [];

        foreach (self::ADVISOR_MODELS as $model => $methods) {
            foreach ($methods as $method) {
                $permissions[] = "{$method}:{$model}";
            }
        }

        return [...$permissions, ...self::ADVISOR_EXTRA];
    }

    public static function grant(): void
    {
        $advisor = collect(self::advisorPermissions())->map(fn (string $name) => Permission::findOrCreate($name));
        collect(self::PRIVILEGED)->each(fn (string $name) => Permission::findOrCreate($name));

        Role::findOrCreate(self::ADVISOR_ROLE)->givePermissionTo($advisor);

        $pageSettings = Permission::findOrCreate('View:DebtConsolidationPagesSettings');
        Role::findOrCreate(self::MARKETING_ROLE)->givePermissionTo($pageSettings);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
