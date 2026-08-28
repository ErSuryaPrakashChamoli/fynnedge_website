<?php

namespace Database\Seeders;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
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

        $personalLoan = LoanProduct::factory()->published()->create([
            'name' => 'Personal Loan',
            'slug' => 'personal-loan',
            'category' => LoanCategory::PersonalLoan,
            'summary' => 'Unsecured funding for personal needs, matched to lenders based on your income, credit profile and location.',
        ]);

        Faq::factory()->for($personalLoan, 'faqable')->state([
            'question' => 'Does checking my eligibility affect my credit score?',
            'answer' => 'No — checking eligibility on FynnEdge uses your self-reported profile and does not perform a hard credit bureau check until you explicitly consent during the application step.',
            'sort_order' => 1,
            'status' => PublishStatus::Published,
        ])->create();

        Faq::factory()->for($personalLoan, 'faqable')->state([
            'question' => 'Does an eligibility result guarantee loan approval?',
            'answer' => 'No. Eligibility results are indicative and subject to the lender\'s final verification, documentation and underwriting.',
            'sort_order' => 2,
            'status' => PublishStatus::Published,
        ])->create();

        LenderProduct::factory()->create([
            'loan_product_id' => $personalLoan->id,
        ]);

        Setting::set('contact_phone', '');
        Setting::set('contact_email', '');
        Setting::set('contact_whatsapp', '');
    }
}
