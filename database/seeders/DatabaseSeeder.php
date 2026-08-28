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

        Faq::query()->updateOrCreate(
            ['faqable_type' => null, 'faqable_id' => null, 'question' => 'What does FynnEdge do?'],
            [
                'answer' => 'FynnEdge is a loan advisory business — we help you compare suitable banks and NBFCs for your loan, based on your profile, rather than you applying to each one separately.',
                'sort_order' => 1,
                'status' => PublishStatus::Published,
            ],
        );

        Faq::query()->updateOrCreate(
            ['faqable_type' => null, 'faqable_id' => null, 'question' => 'Is there a fee to use FynnEdge?'],
            [
                'answer' => 'Checking your eligibility on FynnEdge is free. Any lender fees (processing fees, etc.) are disclosed by the lender before you proceed with an application.',
                'sort_order' => 2,
                'status' => PublishStatus::Published,
            ],
        );

        LenderProduct::query()->firstOrCreate(
            ['loan_product_id' => $personalLoan->id],
            LenderProduct::factory()->make()->toArray() + ['loan_product_id' => $personalLoan->id],
        );

        Page::query()->updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About FynnEdge',
                'excerpt' => 'FynnEdge Advisory (OPC) Pvt Ltd helps you find the right lender for your loan — clearly, and on your terms.',
                'body' => <<<'HTML'
                    <p>FynnEdge Advisory (OPC) Pvt Ltd is a loan advisory and distribution business. We connect
                    customers with suitable banks and NBFCs across personal loans, home loans, business loans,
                    loans against property and credit cards.</p>
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

        $this->call(JourneySeeder::class);
    }
}
