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
            'Achievement',
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

        /*
         * Newsletter work is marketing work, so the Marketing role gets the
         * campaign/template/segment set outright. Two deliberate exclusions:
         *   - Delete:NewsletterSubscriber — a subscriber row is a consent
         *     record and an unsubscribe record; deleting one is how an address
         *     silently becomes mailable again. Read access only.
         *   - View:NewsletterSettings — sender identity and double opt-in
         *     decide the deliverability of every email the domain sends, which
         *     is an admin decision rather than a campaign one.
         */
        $newsletterPermissions = $this->permissionsFor(
            ['NewsletterCampaign', 'NewsletterTemplate', 'NewsletterSegment'],
            ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
        )
            ->merge($this->permissionsFor(['NewsletterSubscriber'], ['ViewAny', 'View']))
            ->merge([Permission::findOrCreate('View:NewsletterDashboard')]);

        Role::findOrCreate('Marketing')->givePermissionTo($newsletterPermissions);
    }

    /**
     * Deliberately ungranted here: `View:StructuredData` and the
     * `*:SchemaTemplate` set (see App\Filament\Pages\StructuredData and
     * SchemaTemplateResource). They shape the JSON-LD every public page emits —
     * including the graph's `@type`s — so which role, if any, gets them is an
     * admin's decision to make in Access Control → Roles, not a default this
     * seeder should presume. Until one is granted, only `super_admin` has them.
     *
     * Note this is NOT a permission the SEO role silently lost: the per-record
     * "Custom JSON-LD" field it could already edit stays under its existing
     * Update:Page / Update:Article / *Seo permissions.
     */

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
