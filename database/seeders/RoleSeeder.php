<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Starter roles for the panel's module-based access control — a working
 * example the user can rename or adjust via Access Control → Roles, not a
 * fixed taxonomy. Permission names follow Shield's generated
 * "{Method}:{Model}" convention (e.g. "Update:Article"); see the *Policy
 * classes in app/Policies for the full set a given model supports.
 *
 * LoanProductContent, LoanProductSeo, LoanLandingPageContent and
 * LoanLandingPageSeo are NOT real Eloquent models — they're restricted
 * editing boundaries (see the matching *Resource classes) that each operate
 * on a real LoanProduct/LoanLandingPage record. Naming their permissions
 * this way keeps them visible and manageable in Access Control → Roles
 * exactly like any other permission, while keeping them entirely separate
 * from Update:LoanProduct / Update:LoanLandingPage (the full business/
 * routing editors, which also cover fields like loan_product_id, group,
 * slug, amount and the calculator/eligibility configuration).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $marketingPermissions = $this->permissionsFor([
            'Article', 'Banner', 'Testimonial', 'CompanyPhoto', 'JobOpening', 'Faq',
            'MarketingSection', 'NavigationLink', 'HowItWorksStep', 'CalculatorPage',
        ], ['ViewAny', 'View', 'Create', 'Update', 'Delete'])
            ->merge($this->permissionsFor(['LoanProductContent', 'LoanLandingPageContent'], ['ViewAny', 'View', 'Update']));

        Role::findOrCreate('Marketing')->syncPermissions($marketingPermissions);

        // LoanProduct/LoanLandingPage (the full resources, including
        // calculator/financial/routing fields) are deliberately NOT granted
        // here — see LoanProductSeoResource / LoanLandingPageSeoResource,
        // which expose only the SEO fields on those same records.
        $seoPermissions = $this->permissionsFor([
            'Article', 'Page',
        ], ['ViewAny', 'View', 'Update'])
            ->merge($this->permissionsFor(['LoanProductSeo', 'LoanLandingPageSeo'], ['ViewAny', 'View', 'Update']));

        Role::findOrCreate('SEO')->syncPermissions($seoPermissions);
    }

    /**
     * @param  array<int, string>  $models
     * @param  array<int, string>  $methods
     * @return Collection<int, Permission>
     */
    private function permissionsFor(array $models, array $methods): Collection
    {
        return collect($models)
            ->crossJoin($methods)
            ->map(fn (array $pair) => "{$pair[1]}:{$pair[0]}")
            ->map(fn (string $name) => Permission::findOrCreate($name));
    }
}
