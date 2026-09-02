<?php

use App\Enums\LenderType;
use App\Enums\LoanCategory;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;

function makeFlexiHybridProduct(): LoanProduct
{
    return LoanProduct::factory()
        ->published()
        ->withCalculatorLimits()
        ->create([
            'slug' => 'flexi-hybrid-term-loan-test',
            'category' => LoanCategory::FlexiHybridTermLoan,
            'name' => 'Flexi Hybrid Term Loan',
        ]);
}

it('renders the hybrid hero as the first content immediately below the header, before any other section', function () {
    $product = makeFlexiHybridProduct();
    LenderProduct::factory()->for($product, 'loanProduct')->create();

    $response = $this->get("/loans/{$product->slug}")->assertOk();

    $response->assertSeeInOrder([
        'id="main-content"',
        'Flexi Hybrid Term Loan',
        'Lower Initial EMI',
        'Compare lenders side by side',
    ], false);
});

it('sends Apply Now straight into the real application journey, not a details page', function () {
    $product = makeFlexiHybridProduct();

    $definition = JourneyDefinition::query()->create([
        'loan_product_id' => $product->id,
        'version' => 1,
        'status' => JourneyDefinitionStatus::Active,
    ]);
    $step = JourneyStep::query()->create([
        'journey_definition_id' => $definition->id,
        'key' => 'basic-details',
        'title' => 'Basic details',
        'order' => 1,
    ]);
    JourneyStepField::query()->create([
        'journey_step_id' => $step->id,
        'key' => 'full_name',
        'label' => 'Full name',
        'type' => FieldType::Text,
        'validation_rules' => ['required'],
        'order' => 1,
    ]);

    $this->get("/loans/{$product->slug}")->assertOk()->assertSee(route('loans.apply', $product), false);

    $response = $this->get(route('loans.apply', $product));

    $response->assertRedirect();
    expect(session('status'))->toBeNull();
});

it('lists every configured lender and shows "Available on request" for unconfigured commercial terms', function () {
    $product = makeFlexiHybridProduct();

    foreach (['Bajaj Finance', 'Tata Capital', 'Piramal Finance', 'Kotak Mahindra Bank'] as $name) {
        $lender = Lender::factory()->create(['name' => $name, 'type' => LenderType::Nbfc]);
        LenderProduct::factory()->create([
            'lender_id' => $lender->id,
            'loan_product_id' => $product->id,
            'min_amount' => null,
            'max_amount' => null,
            'min_tenure_months' => null,
            'max_tenure_months' => null,
            'initial_tenure_months' => null,
            'interest_rate_from' => null,
            'interest_rate_to' => null,
            'processing_fee_note' => null,
            'processing_fee_percent_min' => null,
            'processing_fee_percent_max' => null,
            'processing_fee_flat_amount_min' => null,
            'processing_fee_flat_amount_max' => null,
        ]);
    }

    $response = $this->get("/loans/{$product->slug}")->assertOk();

    $response->assertSee('Bajaj Finance')
        ->assertSee('Tata Capital')
        ->assertSee('Piramal Finance')
        ->assertSee('Kotak Mahindra Bank')
        ->assertSee('Available on request');
});

it('does not change the comparison table for an ordinary, non-hybrid loan product', function () {
    $product = LoanProduct::factory()->published()->create(['category' => LoanCategory::PersonalLoan]);
    LenderProduct::factory()->for($product, 'loanProduct')->create();

    $response = $this->get("/loans/{$product->slug}")->assertOk();

    $response->assertDontSee('Initial tenure')
        ->assertDontSee('Subsequent tenure');
});
