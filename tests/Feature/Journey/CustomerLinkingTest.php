<?php

use App\Models\LoanProduct;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;

function productWithIdentityStep(string $slug): LoanProduct
{
    $product = LoanProduct::factory()->published()->create(['slug' => $slug]);
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);

    foreach (['full_name' => FieldType::Text, 'email' => FieldType::Email, 'phone' => FieldType::Tel] as $key => $type) {
        JourneyStepField::create([
            'journey_step_id' => $step->id, 'key' => $key, 'label' => $key,
            'type' => $type, 'validation_rules' => ['required'], 'order' => 1,
        ]);
    }

    return $product;
}

it('creates a customer the first time an email is submitted', function () {
    $product = productWithIdentityStep('product-a');
    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), [
        'full_name' => 'Priya Shah', 'email' => 'priya@example.com', 'phone' => '9998887777',
    ]);

    $session->refresh();
    expect(Customer::query()->where('email', 'priya@example.com')->count())->toBe(1);
    expect($session->customer->full_name)->toBe('Priya Shah');
});

it('links a second application with the same email to the same customer', function () {
    $productA = productWithIdentityStep('product-a');
    $productB = productWithIdentityStep('product-b');

    $this->get("/loans/{$productA->slug}/apply");
    $sessionA = JourneySession::query()->where('loan_product_id', $productA->id)->firstOrFail();
    $this->post(route('journey.update', $sessionA), [
        'full_name' => 'Priya Shah', 'email' => 'priya@example.com', 'phone' => '9998887777',
    ]);

    $this->get("/loans/{$productB->slug}/apply");
    $sessionB = JourneySession::query()->where('loan_product_id', $productB->id)->firstOrFail();
    $this->post(route('journey.update', $sessionB), [
        'full_name' => 'Priya Shah', 'email' => 'priya@example.com', 'phone' => '9998887777',
    ]);

    expect(Customer::query()->where('email', 'priya@example.com')->count())->toBe(1);

    $sessionA->refresh();
    $sessionB->refresh();
    expect($sessionA->customer_id)->toBe($sessionB->customer_id);

    $customer = Customer::query()->where('email', 'priya@example.com')->sole();
    expect($customer->journeySessions()->count())->toBe(2);
});

it('does not create a customer when no email has been submitted yet', function () {
    $product = LoanProduct::factory()->published()->create();
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $step = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic', 'order' => 1]);
    JourneyStepField::create([
        'journey_step_id' => $step->id, 'key' => 'city', 'label' => 'City',
        'type' => FieldType::Text, 'validation_rules' => ['required'], 'order' => 1,
    ]);

    $this->get("/loans/{$product->slug}/apply");
    $session = JourneySession::query()->where('loan_product_id', $product->id)->firstOrFail();

    $this->post(route('journey.update', $session), ['city' => 'Pune']);

    $session->refresh();
    expect($session->customer_id)->toBeNull();
    expect(Customer::query()->count())->toBe(0);
});
