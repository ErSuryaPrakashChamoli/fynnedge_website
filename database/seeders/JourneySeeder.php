<?php

namespace Database\Seeders;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;
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
            ['key' => 'full_name', 'label' => 'Full name', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120']],
            ['key' => 'email', 'label' => 'Email', 'type' => FieldType::Email, 'validation_rules' => ['required', 'email', 'max:190']],
            ['key' => 'phone', 'label' => 'Phone number', 'type' => FieldType::Tel, 'validation_rules' => ['required', 'string', 'max:20']],
            ['key' => 'date_of_birth', 'label' => 'Date of birth', 'type' => FieldType::Date, 'validation_rules' => ['required', 'date', 'before:-21 years'], 'help_text' => 'You must be at least 21 years old.'],
            ['key' => 'city', 'label' => 'City', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120']],
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
            ['key' => 'company_name', 'label' => 'Company name', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:150'], 'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'salaried']],
            ['key' => 'designation', 'label' => 'Designation', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:120'], 'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'salaried']],
            ['key' => 'business_name', 'label' => 'Business name', 'type' => FieldType::Text, 'validation_rules' => ['required', 'string', 'max:150'], 'conditional_on' => ['field' => 'employment_type', 'operator' => '=', 'value' => 'self-employed']],
            ['key' => 'employment_vintage_years', 'label' => 'Years in current job/business', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:0', 'max:60']],
        ];
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
                'key' => 'credit_check_consent',
                'label' => "I consent to FynnEdge and its lending partners checking my credit information as part of this {$productLabel} application.",
                'type' => FieldType::Checkbox,
                'validation_rules' => ['accepted'],
            ],
        ];
    }
}
