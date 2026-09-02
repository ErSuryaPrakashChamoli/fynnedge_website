<?php

namespace Database\Seeders;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
use App\Support\Options\CityOptions;
use App\Support\Options\EmployerOptions;
use Illuminate\Database\Seeder;

class JourneySeeder extends Seeder
{
    public function run(): void
    {
        $personalLoan = LoanProduct::query()->where('slug', 'personal-loan')->firstOrFail();

        $homeLoan = LoanProduct::query()->updateOrCreate(
            ['slug' => 'home-loan'],
            [
                'name' => 'Home Loan',
                'category' => LoanCategory::HomeLoan,
                'summary' => 'Finance a new home, resale property or construction, matched to lenders based on the property and your profile.',
                'body' => '<p>Whether you\'re buying a ready-to-move apartment, an under-construction unit, or building on '
                    .'your own plot, FynnEdge helps you compare home loan offers from multiple lenders side by side.</p>',
                'features' => ['Long repayment tenure', 'Competitive interest rates', 'Funding for purchase, construction or resale'],
                'eligibility_points' => ['Salaried or self-employed with a regular income', 'Property must be in a lender-serviceable location', 'Own contribution (down payment) typically required'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Income Proof', 'Property Documents', 'Bank Statements (last 6 months)'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Property & legal verification', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 500_000,
                'max_amount' => 100_000_000,
                'default_amount' => 4_000_000,
                'min_tenure_months' => 60,
                'max_tenure_months' => 360,
                'default_tenure_months' => 240,
                'min_interest_rate' => 7.00,
                'max_interest_rate' => 10.50,
                'default_interest_rate' => 7.00,
                'interest_rate_note' => null,
            ],
        );

        LenderProduct::query()->firstOrCreate(
            ['loan_product_id' => $homeLoan->id],
            LenderProduct::factory()->make([
                'loan_product_id' => $homeLoan->id,
                'min_amount' => 500_000,
                'max_amount' => 100_000_000,
                'min_tenure_months' => 60,
                'max_tenure_months' => 360,
                'interest_rate_from' => 7.00,
                'interest_rate_to' => 10.50,
            ])->toArray(),
        );

        $carLoan = LoanProduct::query()->updateOrCreate(
            ['slug' => 'car-loan'],
            [
                'name' => 'Car Loan',
                'category' => LoanCategory::CarLoan,
                'summary' => 'Finance a new or used car, matched to lenders based on the vehicle and your income profile.',
                'body' => '<p>FynnEdge helps you compare car loan offers from multiple lenders, whether you\'re buying '
                    .'new or pre-owned, based on the vehicle\'s value and your income profile.</p>',
                'features' => ['Funding for new and used cars', 'Competitive interest rates', 'Fast approval turnaround'],
                'eligibility_points' => ['Salaried or self-employed with a regular income', 'Minimum age as per lender policy', 'Reasonable existing obligations relative to income'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Income Proof', 'Bank Statements (last 3 months)', 'Vehicle Quotation/Invoice'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Upload documents', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 100_000,
                'max_amount' => 10_000_000,
                'default_amount' => 800_000,
                'min_tenure_months' => 12,
                'max_tenure_months' => 96,
                'default_tenure_months' => 60,
                'min_interest_rate' => 9.10,
                'max_interest_rate' => 15.00,
                'default_interest_rate' => 9.10,
                'interest_rate_note' => null,
            ],
        );

        $lap = LoanProduct::query()->updateOrCreate(
            ['slug' => 'loan-against-property'],
            [
                'name' => 'Loan Against Property',
                'category' => LoanCategory::LoanAgainstProperty,
                'summary' => 'Unlock funds against a residential or commercial property you own, for business or personal needs.',
                'body' => '<p>A loan against property lets you borrow against an owned property while continuing to use it — '
                    .'typically at lower rates than an unsecured loan, for larger amounts and longer tenures.</p>',
                'features' => ['Higher loan amounts than unsecured loans', 'Longer repayment tenure', 'Use funds for any purpose'],
                'eligibility_points' => ['Clear ownership of the property being pledged', 'Regular income to support repayment', 'Property must be in a lender-serviceable location'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Property Ownership Documents', 'Income Proof', 'Bank Statements (last 6 months)'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Property valuation & verification', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 500_000,
                'max_amount' => 75_000_000,
                'default_amount' => 3_000_000,
                'min_tenure_months' => 60,
                'max_tenure_months' => 180,
                'default_tenure_months' => 120,
                'min_interest_rate' => 9.50,
                'max_interest_rate' => 14.00,
                'default_interest_rate' => 9.50,
                'interest_rate_note' => null,
            ],
        );

        $businessLoan = LoanProduct::query()->updateOrCreate(
            ['slug' => 'business-loan'],
            [
                'name' => 'Business Loan',
                'category' => LoanCategory::BusinessLoan,
                'summary' => 'Working capital or expansion funding for your business, matched to lenders based on your turnover and banking history.',
                'body' => '<p>From working capital to equipment purchase or expansion, FynnEdge helps growing businesses '
                    .'compare loan offers from banks and NBFCs based on turnover, vintage and banking history.</p>',
                'features' => ['Collateral-free options available', 'Flexible use of funds', 'Fast turnaround for eligible businesses'],
                'eligibility_points' => ['Minimum business vintage (varies by lender)', 'Healthy annual turnover', 'Reasonable existing business obligations'],
                'documents_required' => ['PAN Card', 'Business Registration Proof', 'GST Returns', 'Bank Statements (last 12 months)', 'ITR (last 2 years)'],
                'process_steps' => ['Check eligibility', 'Compare lenders', 'Apply online', 'Business & banking verification', 'Get sanctioned'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 100_000,
                'max_amount' => 20_000_000,
                'default_amount' => 1_000_000,
                'min_tenure_months' => 12,
                'max_tenure_months' => 84,
                'default_tenure_months' => 60,
                'min_interest_rate' => 9.60,
                'max_interest_rate' => 24.00,
                'default_interest_rate' => 9.60,
                'interest_rate_note' => null,
            ],
        );

        $creditCard = LoanProduct::query()->updateOrCreate(
            ['slug' => 'credit-card'],
            [
                'name' => 'Credit Card',
                'category' => LoanCategory::CreditCard,
                'summary' => 'Compare credit cards matched to your income and spending profile — from cashback to travel rewards.',
                'body' => '<p>FynnEdge helps you find a credit card suited to your income and spending habits, from '
                    .'everyday cashback cards to premium travel and rewards cards.</p>',
                'features' => ['Rewards, cashback and travel options', 'Fast, profile-based matching', 'Compare multiple issuers at once'],
                'eligibility_points' => ['Salaried or self-employed with a regular income', 'Minimum age as per issuer policy', 'Existing card obligations considered'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Income Proof'],
                'process_steps' => ['Check eligibility', 'Compare cards', 'Apply online', 'KYC verification', 'Card issuance'],
                'status' => PublishStatus::Published,
                'published_at' => now(),
            ],
        );

        // Seeded by CalculatorLoanProductSeeder, which runs before this seeder.
        $goldLoan = LoanProduct::query()->where('slug', 'gold-loan')->firstOrFail();
        $twoWheelerLoan = LoanProduct::query()->where('slug', 'two-wheeler-loan')->firstOrFail();
        $termLoan = LoanProduct::query()->where('slug', 'term-loan')->firstOrFail();
        $tractorLoan = LoanProduct::query()->where('slug', 'tractor-loan')->firstOrFail();
        $mudraLoan = LoanProduct::query()->where('slug', 'mudra-loan')->firstOrFail();

        // Flexi Hybrid Term Loan: a distinct LoanCategory (not a second product
        // under TermLoan) so it gets its own slot in the header mega menu and
        // its own calculator preset — see .ai/rules/calculators-models.md on
        // why LoanMegaMenu::categories() keys one product per category.
        // Amount/tenure/rate bounds here are the same illustrative-placeholder
        // convention every other seeded LoanProduct uses; per-lender commercial
        // terms are deliberately left unset in FlexiHybridTermLoanSeeder, since
        // no real Bajaj/Tata Capital/Piramal Finance/Kotak figures for this
        // product were sourced for this rollout.
        $flexiHybridTermLoan = LoanProduct::query()->updateOrCreate(
            ['slug' => 'flexi-hybrid-term-loan'],
            [
                'name' => 'Flexi Hybrid Term Loan',
                'category' => LoanCategory::FlexiHybridTermLoan,
                'marketing_headline' => 'Lower Initial EMI. Flexible Access. Smarter Repayment.',
                'summary' => 'A two-stage term loan: pay interest-only for an initial tenure, then principal + interest for the rest — compare Bajaj Finance, Tata Capital, Piramal Finance and Kotak.',
                'body' => '<p>A Flexi Hybrid Term Loan repays in two stages. During the initial tenure, you typically pay '
                    .'interest only, keeping your early monthly outflow lower than a conventional EMI. Once the initial '
                    .'tenure ends, the loan converts to standard principal + interest EMIs for the remaining (subsequent) '
                    .'tenure until the loan is fully repaid. Exact initial tenure, subsequent tenure, utilisation terms and '
                    .'repayment flexibility vary by lender — compare them below before you apply.</p>',
                'features' => ['Interest-only initial tenure', 'Principal + interest subsequent tenure', 'Compare multiple lenders side by side'],
                'eligibility_points' => ['Salaried or self-employed with a regular income', 'Reasonable existing obligations relative to income', 'Exact criteria vary by lender'],
                'documents_required' => ['PAN Card', 'Address Proof', 'Income Proof', 'Bank Statements (last 3 months)'],
                'process_steps' => ['Get sanctioned based on eligibility', 'Use the facility as permitted by your lender', 'Repay via the initial then subsequent tenure structure'],
                'cta_label' => 'Apply Now',
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'min_amount' => 100_000,
                'max_amount' => 20_000_000,
                'default_amount' => 1_000_000,
                'min_tenure_months' => 24,
                'max_tenure_months' => 72,
                'default_tenure_months' => 60,
                'default_initial_tenure_months' => 12,
                'min_interest_rate' => 10.00,
                'max_interest_rate' => 18.00,
                'default_interest_rate' => 10.00,
                'interest_rate_note' => 'Interest-only for the initial tenure, then principal + interest — exact terms vary by lender.',
            ],
        );

        $this->createJourney($personalLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'employment-details', 'title' => 'Employment details', 'fields' => $this->employmentFields()],
            ['key' => 'income-details', 'title' => 'Income details', 'fields' => $this->incomeFields()],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:10000']],
                ['key' => 'loan_purpose', 'label' => 'Purpose', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'wedding', 'label' => 'Wedding'],
                    ['value' => 'medical', 'label' => 'Medical'],
                    ['value' => 'travel', 'label' => 'Travel'],
                    ['value' => 'home-renovation', 'label' => 'Home renovation'],
                    ['value' => 'debt-consolidation', 'label' => 'Debt consolidation'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:3', 'max:84']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('personal loan')],
        ]);

        $this->createJourney($homeLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'employment-details', 'title' => 'Employment details', 'fields' => $this->employmentFields()],
            ['key' => 'income-details', 'title' => 'Income details', 'fields' => $this->incomeFields()],
            ['key' => 'property-details', 'title' => 'Property details', 'fields' => [
                ['key' => 'property_type', 'label' => 'Property type', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'apartment', 'label' => 'Apartment'],
                    ['value' => 'independent-house', 'label' => 'Independent house'],
                    ['value' => 'plot', 'label' => 'Plot'],
                ]],
                ['key' => 'property_status', 'label' => 'Property status', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'ready-to-move', 'label' => 'Ready to move'],
                    ['value' => 'under-construction', 'label' => 'Under construction'],
                    ['value' => 'resale', 'label' => 'Resale'],
                ]],
                ['key' => 'property_value', 'label' => 'Estimated property value (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'own_contribution', 'label' => 'Your own contribution (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0'], 'help_text' => 'Also known as the down payment.'],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:12', 'max:360']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('home loan')],
        ]);

        $this->createJourney($carLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'employment-details', 'title' => 'Employment details', 'fields' => $this->employmentFields()],
            ['key' => 'income-details', 'title' => 'Income details', 'fields' => $this->incomeFields()],
            ['key' => 'vehicle-details', 'title' => 'Vehicle details', 'fields' => [
                ['key' => 'vehicle_condition', 'label' => 'Vehicle condition', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'new', 'label' => 'New'],
                    ['value' => 'used', 'label' => 'Used'],
                ]],
                ['key' => 'vehicle_value', 'label' => 'Estimated on-road price (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'own_contribution', 'label' => 'Your own contribution (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0'], 'help_text' => 'Also known as the down payment.'],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:12', 'max:96']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('car loan')],
        ]);

        $this->createJourney($lap, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'employment-details', 'title' => 'Employment details', 'fields' => $this->employmentFields()],
            ['key' => 'income-details', 'title' => 'Income details', 'fields' => $this->incomeFields()],
            ['key' => 'property-details', 'title' => 'Property details', 'fields' => [
                ['key' => 'property_type', 'label' => 'Property type', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'residential', 'label' => 'Residential'],
                    ['value' => 'commercial', 'label' => 'Commercial'],
                ]],
                ['key' => 'property_ownership', 'label' => 'Ownership', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'self', 'label' => 'Self-owned'],
                    ['value' => 'co-owned', 'label' => 'Co-owned'],
                ]],
                ['key' => 'property_value', 'label' => 'Estimated property value (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'existing_loan_on_property', 'label' => 'Any existing loan on this property?', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
                ['key' => 'existing_loan_outstanding', 'label' => 'Outstanding amount (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0'], 'conditional_on' => ['field' => 'existing_loan_on_property', 'operator' => '=', 'value' => 'yes']],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'loan_purpose', 'label' => 'Purpose', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'business-expansion', 'label' => 'Business expansion'],
                    ['value' => 'debt-consolidation', 'label' => 'Debt consolidation'],
                    ['value' => 'education', 'label' => 'Education'],
                    ['value' => 'personal', 'label' => 'Personal'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:12', 'max:180']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('loan against property')],
        ]);

        $this->createJourney($businessLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'description' => 'Tell us about yourself as the business owner or promoter.', 'fields' => $this->basicDetailsFields()],
            ['key' => 'business-profile', 'title' => 'Business profile', 'fields' => [
                ['key' => 'business_name', 'label' => 'Business name', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:150']],
                ['key' => 'business_type', 'label' => 'Business constitution', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'proprietorship', 'label' => 'Proprietorship'],
                    ['value' => 'partnership', 'label' => 'Partnership'],
                    ['value' => 'pvt-ltd', 'label' => 'Private Limited'],
                    ['value' => 'llp', 'label' => 'LLP'],
                ]],
                ['key' => 'industry', 'label' => 'Industry', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120']],
                ['key' => 'business_vintage_years', 'label' => 'Years in business', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0', 'max:100']],
            ]],
            ['key' => 'business-financials', 'title' => 'Business financials', 'fields' => [
                ['key' => 'annual_turnover', 'label' => 'Annual turnover (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0']],
                ['key' => 'is_gst_registered', 'label' => 'GST registered?', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
                ['key' => 'gst_number', 'label' => 'GST number', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:20'], 'conditional_on' => ['field' => 'is_gst_registered', 'operator' => '=', 'value' => 'yes']],
                ['key' => 'is_profitable', 'label' => 'Is the business currently profitable?', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'description' => 'Existing business loans or EMIs, if any.', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:50000']],
                ['key' => 'loan_purpose', 'label' => 'Purpose', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'working-capital', 'label' => 'Working capital'],
                    ['value' => 'equipment', 'label' => 'Equipment purchase'],
                    ['value' => 'expansion', 'label' => 'Business expansion'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:3', 'max:120']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('business loan')],
        ]);

        $this->createJourney($creditCard, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'employment-details', 'title' => 'Employment details', 'fields' => $this->employmentFields()],
            ['key' => 'income-details', 'title' => 'Income details', 'fields' => $this->incomeFields()],
            ['key' => 'existing-cards', 'title' => 'Existing cards', 'fields' => [
                ['key' => 'has_existing_cards', 'label' => 'Do you have any existing credit cards?', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
                ['key' => 'existing_cards_count', 'label' => 'How many?', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:1', 'max:20'], 'conditional_on' => ['field' => 'has_existing_cards', 'operator' => '=', 'value' => 'yes']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with card issuers.', 'fields' => $this->consentField('credit card')],
        ]);

        // Gold Loan skips employment/income entirely — it's secured against the gold
        // itself, so lenders assess the pledged item, not the applicant's salary slip.
        $this->createJourney($goldLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'gold-details', 'title' => 'Gold details', 'fields' => [
                ['key' => 'gold_item_type', 'label' => 'Gold item type', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'jewellery', 'label' => 'Jewellery'],
                    ['value' => 'coins', 'label' => 'Coins'],
                    ['value' => 'bars', 'label' => 'Bars'],
                ]],
                ['key' => 'estimated_gold_weight_grams', 'label' => 'Estimated gold weight (grams)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:1']],
                ['key' => 'loan_purpose', 'label' => 'Purpose', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'medical', 'label' => 'Medical emergency'],
                    ['value' => 'business', 'label' => 'Business working capital'],
                    ['value' => 'agriculture', 'label' => 'Agricultural needs'],
                    ['value' => 'debt-consolidation', 'label' => 'Debt consolidation'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:10000']],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:3', 'max:36']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('gold loan')],
        ]);

        $this->createJourney($twoWheelerLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'employment-details', 'title' => 'Employment details', 'fields' => $this->employmentFields()],
            ['key' => 'income-details', 'title' => 'Income details', 'fields' => $this->incomeFields()],
            ['key' => 'vehicle-details', 'title' => 'Vehicle details', 'fields' => [
                ['key' => 'vehicle_condition', 'label' => 'Vehicle condition', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'new', 'label' => 'New'],
                    ['value' => 'used', 'label' => 'Used'],
                ]],
                ['key' => 'vehicle_value', 'label' => 'Estimated on-road price (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:20000']],
                ['key' => 'own_contribution', 'label' => 'Your own contribution (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0'], 'help_text' => 'Also known as the down payment.'],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:20000']],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:6', 'max:48']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('two wheeler loan')],
        ]);

        $this->createJourney($termLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'description' => 'Tell us about yourself as the business owner or promoter.', 'fields' => $this->basicDetailsFields()],
            ['key' => 'business-profile', 'title' => 'Business profile', 'fields' => [
                ['key' => 'business_name', 'label' => 'Business name', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:150']],
                ['key' => 'business_type', 'label' => 'Business constitution', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'proprietorship', 'label' => 'Proprietorship'],
                    ['value' => 'partnership', 'label' => 'Partnership'],
                    ['value' => 'pvt-ltd', 'label' => 'Private Limited'],
                    ['value' => 'llp', 'label' => 'LLP'],
                ]],
                ['key' => 'industry', 'label' => 'Industry', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120']],
                ['key' => 'business_vintage_years', 'label' => 'Years in business', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0', 'max:100']],
            ]],
            ['key' => 'business-financials', 'title' => 'Business financials', 'fields' => [
                ['key' => 'annual_turnover', 'label' => 'Annual turnover (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0']],
                ['key' => 'is_gst_registered', 'label' => 'GST registered?', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ]],
                ['key' => 'gst_number', 'label' => 'GST number', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:20'], 'conditional_on' => ['field' => 'is_gst_registered', 'operator' => '=', 'value' => 'yes']],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'description' => 'Existing business loans or EMIs, if any.', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'loan_purpose', 'label' => 'Purpose', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'working-capital', 'label' => 'Working capital'],
                    ['value' => 'equipment', 'label' => 'Equipment purchase'],
                    ['value' => 'expansion', 'label' => 'Business expansion'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:12', 'max:120']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('term loan')],
        ]);

        // Tractor Loan swaps employment/income for an agricultural-income profile —
        // most applicants are farmers, not salaried/self-employed in the usual sense.
        $this->createJourney($tractorLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'agri-profile', 'title' => 'Agricultural profile', 'fields' => [
                ['key' => 'land_holding_acres', 'label' => 'Land holding (acres)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0']],
                ['key' => 'land_ownership', 'label' => 'Land ownership', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'owned', 'label' => 'Owned'],
                    ['value' => 'leased', 'label' => 'Leased'],
                ]],
                ['key' => 'annual_farm_income', 'label' => 'Annual farm income (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0']],
            ]],
            ['key' => 'tractor-details', 'title' => 'Tractor details', 'fields' => [
                ['key' => 'tractor_condition', 'label' => 'Tractor condition', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'new', 'label' => 'New'],
                    ['value' => 'used', 'label' => 'Used'],
                ]],
                ['key' => 'tractor_value', 'label' => 'Estimated on-road price (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'own_contribution', 'label' => 'Your own contribution (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0'], 'help_text' => 'Also known as the down payment.'],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:12', 'max:84']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('tractor loan')],
        ]);

        // Mudra Loan asks which of the scheme's three official tiers (Shishu/Kishor/
        // Tarun) the applicant is requesting, rather than a free-form loan amount.
        $this->createJourney($mudraLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'description' => 'Tell us about yourself as the business owner.', 'fields' => $this->basicDetailsFields()],
            ['key' => 'business-profile', 'title' => 'Business profile', 'fields' => [
                ['key' => 'business_name', 'label' => 'Business name', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:150']],
                ['key' => 'business_type', 'label' => 'Business constitution', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'proprietorship', 'label' => 'Proprietorship'],
                    ['value' => 'partnership', 'label' => 'Partnership'],
                ]],
                ['key' => 'industry', 'label' => 'Industry', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120']],
                ['key' => 'business_vintage_years', 'label' => 'Years in business (0 if not yet started)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0', 'max:100']],
            ]],
            ['key' => 'mudra-tier', 'title' => 'Mudra tier', 'description' => 'Which stage of funding are you applying for?', 'fields' => [
                ['key' => 'mudra_tier', 'label' => 'Requested tier', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'shishu', 'label' => 'Shishu — up to ₹50,000'],
                    ['value' => 'kishor', 'label' => 'Kishor — ₹50,000 to ₹5,00,000'],
                    ['value' => 'tarun', 'label' => 'Tarun — ₹5,00,000 to ₹10,00,000'],
                ]],
                ['key' => 'loan_purpose', 'label' => 'Purpose', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                    ['value' => 'setup', 'label' => 'Micro enterprise setup'],
                    ['value' => 'working-capital', 'label' => 'Working capital'],
                    ['value' => 'equipment', 'label' => 'Equipment purchase'],
                    ['value' => 'other', 'label' => 'Other'],
                ]],
            ]],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('Mudra loan')],
        ]);

        $this->createJourney($flexiHybridTermLoan, [
            ['key' => 'basic-details', 'title' => 'Basic details', 'fields' => $this->basicDetailsFields()],
            ['key' => 'employment-details', 'title' => 'Employment details', 'fields' => $this->employmentFields()],
            ['key' => 'income-details', 'title' => 'Income details', 'fields' => $this->incomeFields()],
            ['key' => 'existing-obligations', 'title' => 'Existing obligations', 'fields' => $this->obligationsFields()],
            ['key' => 'loan-requirement', 'title' => 'Loan requirement', 'fields' => [
                ['key' => 'loan_amount', 'label' => 'Loan amount required (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:100000']],
                ['key' => 'preferred_tenure_months', 'label' => 'Preferred total tenure (months)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'integer', 'min:24', 'max:72']],
            ]],
            ['key' => 'consent', 'title' => 'Consent', 'description' => 'One last step before we match you with lenders.', 'fields' => $this->consentField('Flexi Hybrid Term Loan')],
        ]);
    }

    /**
     * @param  array<int, array{key: string, title: string, description?: string, fields: array<int, array<string, mixed>>}>  $stepDefinitions
     */
    private function createJourney(LoanProduct $product, array $stepDefinitions): void
    {
        $definition = JourneyDefinition::query()->updateOrCreate(
            ['loan_product_id' => $product->id, 'version' => 1],
            ['status' => JourneyDefinitionStatus::Active],
        );

        foreach ($stepDefinitions as $stepIndex => $stepDefinition) {
            $step = JourneyStep::query()->updateOrCreate(
                ['journey_definition_id' => $definition->id, 'key' => $stepDefinition['key']],
                [
                    'title' => $stepDefinition['title'],
                    'description' => $stepDefinition['description'] ?? null,
                    'order' => $stepIndex + 1,
                ],
            );

            foreach ($stepDefinition['fields'] as $fieldIndex => $fieldDefinition) {
                JourneyStepField::query()->updateOrCreate(
                    ['journey_step_id' => $step->id, 'key' => $fieldDefinition['key']],
                    [
                        'label' => $fieldDefinition['label'],
                        'type' => $fieldDefinition['type'],
                        'options' => $fieldDefinition['options'] ?? null,
                        'validation_rules' => $fieldDefinition['validation_rules'],
                        'help_text' => $fieldDefinition['help_text'] ?? null,
                        'order' => $fieldIndex + 1,
                        'conditional_on' => $fieldDefinition['conditional_on'] ?? null,
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function basicDetailsFields(): array
    {
        return [
            ['key' => 'full_name', 'label' => 'Full name (as per PAN)', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120']],
            ['key' => 'phone', 'label' => 'Mobile number', 'type' => FieldType::Tel, 'validation_rules' => ['required', 'regex:/^[6-9]\d{9}$/'], 'help_text' => 'A 10-digit Indian mobile number, verified with an OTP.'],
            ['key' => 'email', 'label' => 'Email', 'type' => FieldType::Email, 'validation_rules' => ['required', 'email', 'max:190']],
            ['key' => 'date_of_birth', 'label' => 'Date of birth', 'type' => FieldType::Date, 'validation_rules' => ['required', 'date', 'before:-21 years'], 'help_text' => 'You must be at least 21 years old.'],
            ['key' => 'city', 'label' => 'City', 'type' => FieldType::SearchableSelect, 'validation_rules' => ['required', 'string', 'max:120'], 'options' => $this->cityOptions()],
            [
                'key' => 'platform_consent',
                // Rendered with real hyperlinks by field.blade.php's Checkbox case
                // (key === 'platform_consent'); this text is the escaped fallback used
                // wherever $field->label is read verbatim (e.g. admin previews). A
                // lighter-weight companion to consentField()'s 'credit_check_consent' —
                // shown right at the start of the journey, before any PAN or credit
                // information is collected, so a customer sees and accepts these terms
                // from their very first step rather than only at the end.
                'label' => 'By submitting this form, you have read and agree to the Credit Report Terms of Use, Terms of Use & Privacy Policy.',
                'type' => FieldType::Checkbox,
                'validation_rules' => ['accepted'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employmentFields(): array
    {
        return [
            ['key' => 'employment_type', 'label' => 'Employment type', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                ['value' => 'salaried', 'label' => 'Salaried'],
                ['value' => 'self-employed', 'label' => 'Self-employed'],
            ]],
            ['key' => 'company_name', 'label' => 'Company name', 'type' => FieldType::SearchableSelect, 'validation_rules' => ['required', 'string', 'max:150'], 'options' => $this->companyOptions(), 'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'salaried']],
            ['key' => 'designation', 'label' => 'Designation', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120'], 'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'salaried']],
            ['key' => 'business_name', 'label' => 'Business name', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:150'], 'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'self-employed']],
            // Label is re-rendered client-side as "Years in current job" vs "Years in current
            // business" based on employment_type (x-journey.field) — this is the fallback shown
            // before Alpine hydrates. Key stays singular; EligibilitySeeder's lender criteria
            // key off 'employment_vintage_years' regardless of employment type.
            ['key' => 'employment_vintage_years', 'label' => 'Years in current job', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0', 'max:60']],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function cityOptions(): array
    {
        return CityOptions::all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function companyOptions(): array
    {
        return EmployerOptions::all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function incomeFields(): array
    {
        return [
            ['key' => 'monthly_income', 'label' => 'Monthly income (₹)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0']],
            ['key' => 'other_monthly_income', 'label' => 'Other monthly income (₹, optional)', 'type' => FieldType::Number, 'validation_rules' => ['nullable', 'numeric', 'min:0']],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obligationsFields(): array
    {
        return [
            ['key' => 'has_existing_emis', 'label' => 'Do you have existing loan EMIs?', 'type' => FieldType::Select, 'validation_rules' => ['required'], 'options' => [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'no', 'label' => 'No'],
            ]],
            ['key' => 'existing_emi_amount', 'label' => 'Total existing EMI amount (₹/month)', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0'], 'conditional_on' => ['field' => 'has_existing_emis', 'operator' => '=', 'value' => 'yes']],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function consentField(string $productLabel): array
    {
        return [
            [
                'key' => 'pan_number',
                'label' => 'PAN',
                'type' => FieldType::Text,
                'validation_rules' => ['required', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/i'],
                'help_text' => 'Used only to run your credit check, with your consent below.',
            ],
            [
                'key' => 'credit_check_consent',
                // Rendered with real hyperlinks by field.blade.php's Checkbox case
                // (key === 'credit_check_consent'); this text is the escaped fallback
                // used wherever $field->label is read verbatim (e.g. admin previews).
                'label' => "I consent to FynnEdge and its lending partners checking my credit information as part of this {$productLabel} application, and confirm I have read and agree to the Credit Report Terms of Use, Terms of Use & Privacy Policy.",
                'type' => FieldType::Checkbox,
                'validation_rules' => ['accepted'],
            ],
        ];
    }
}
