<?php

namespace Database\Seeders;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'fynnedge@gmail.com']);
        $isNewAdmin = ! $admin->exists;

        $admin->name = 'FynnEdge Admin';
        $admin->is_admin = true;

        if ($isNewAdmin) {
            $password = Str::password(20);
            $admin->password = $password;
        }

        $admin->save();
        $admin->assignRole(Role::findOrCreate('super_admin'));

        if ($isNewAdmin && $this->command) {
            $this->command->warn("Admin account created — email: fynnedge@gmail.com / password: {$password}");
            $this->command->warn('This password is shown once and is not stored in any committed file.');
        }

        $personalLoan = LoanProduct::query()->updateOrCreate(
            ['slug' => 'personal-loan'],
            [
                'name' => 'Personal Loan',
                'category' => LoanCategory::PersonalLoan,
                'summary' => 'Unsecured funding for personal needs, matched to lenders based on your income, credit profile and location.',
                'body' => '<p>A personal loan from FynnEdge\'s lending partners can help fund weddings, medical expenses, '
                    .'travel, home renovation, or debt consolidation — without pledging any collateral.</p>',
                'features' => ['No collateral required', 'Flexible tenure', 'Funds for any personal purpose'],
                'eligibility_points' => ['Salaried or self-employed with a regular income', 'Minimum age 21, maximum age at loan maturity per lender policy', 'Reasonable existing obligations relative to income'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Bank Statements (last 3 months)', 'Income Proof'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Upload documents', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 25_000,
                'max_amount' => 5_000_000,
                'default_amount' => 500_000,
                'min_tenure_months' => 12,
                'max_tenure_months' => 84,
                'default_tenure_months' => 36,
                'min_interest_rate' => 10.49,
                'max_interest_rate' => 24.00,
                'default_interest_rate' => 10.49,
                'interest_rate_note' => null,
            ],
        );

        Faq::query()->updateOrCreate(
            ['faqable_type' => LoanProduct::class, 'faqable_id' => $personalLoan->id, 'sort_order' => 1],
            [
                'question' => 'Does checking my eligibility affect my credit score?',
                'answer' => 'No — checking eligibility on FynnEdge uses your self-reported profile and does not perform a hard credit bureau check until you explicitly consent during the application step.',
                'status' => PublishStatus::Published,
            ],
        );

        Faq::query()->updateOrCreate(
            ['faqable_type' => LoanProduct::class, 'faqable_id' => $personalLoan->id, 'sort_order' => 2],
            [
                'question' => 'Does an eligibility result guarantee loan approval?',
                'answer' => 'No. Eligibility results are indicative and subject to the lender\'s final verification, documentation and underwriting.',
                'status' => PublishStatus::Published,
            ],
        );

        LenderProduct::query()->firstOrCreate(
            ['loan_product_id' => $personalLoan->id],
            // Overriding loan_product_id via array union (`+`) after toArray() silently no-ops —
            // the key already exists from the factory's own LoanProduct::factory() default, and
            // `+` keeps the left-hand value on a key collision. It must be passed into make()
            // itself so the factory never resolves (and persists) a throwaway LoanProduct at all.
            LenderProduct::factory()->make(['loan_product_id' => $personalLoan->id])->toArray(),
        );

        Page::query()->updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About FynnEdge',
                'excerpt' => 'FynnEdge Advisory (OPC) Pvt Ltd helps you find the right lender for your loan — clearly, and on your terms.',
                'body' => <<<'HTML'
                    <p>FynnEdge Advisory (OPC) Pvt Ltd is a loan advisory and distribution business. We connect
                    customers with suitable banks and NBFCs across personal loans, home loans, car loans,
                    business loans, loans against property and credit cards.</p>
                    <h2>How we work</h2>
                    <p>Rather than applying to lenders one at a time, you share your profile once. We match it
                    against participating lenders' published criteria and show you which ones you're likely
                    eligible for, along with plain-language reasons — before you commit to a formal application.</p>
                    <p>Eligibility results shown on FynnEdge are indicative and subject to each lender's own
                    verification, documentation and underwriting process.</p>
                    HTML,
                'status' => PublishStatus::Published,
                'published_at' => now(),
            ],
        );

        Setting::set('contact_phone', Setting::get('contact_phone', ''));
        Setting::set('contact_email', Setting::get('contact_email', ''));
        Setting::set('contact_whatsapp', Setting::get('contact_whatsapp', ''));
        Setting::set('contact_address', Setting::get('contact_address', ''));
        Setting::set('contact_map_url', Setting::get('contact_map_url', ''));

        $this->call(RoleSeeder::class);
        $this->call(CalculatorLoanProductSeeder::class);
        $this->call(JourneySeeder::class);
        $this->call(LoanLandingPageSeeder::class);
        $this->call(EligibilitySeeder::class);
        $this->call(LenderRosterSeeder::class);
        $this->call(FlexiHybridTermLoanSeeder::class);
        $this->call(DocumentRequirementSeeder::class);
        $this->call(LegalPageSeeder::class);
        $this->call(CalculatorPageSeeder::class);
        $this->call(JobOpeningSeeder::class);
        $this->call(ArticleSeeder::class);
        $this->call(FaqSeeder::class);
        $this->call(TestimonialSeeder::class);
    }
}
