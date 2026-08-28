<?php

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\JourneySessionsRelationManager;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('renders the customer index and edit pages', function () {
    $this->actingAs($this->admin);

    $customer = Customer::factory()->create();

    $this->get('/admin/customers')->assertOk();
    $this->get("/admin/customers/{$customer->public_id}/edit")->assertOk();
});

it('cannot create customers manually', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/customers/create')->assertNotFound();
});

it('shows a customer applications in the relation manager', function () {
    $this->actingAs($this->admin);

    $customer = Customer::factory()->create();
    $product = LoanProduct::factory()->create();
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $session = JourneySession::create(['loan_product_id' => $product->id, 'journey_definition_id' => $definition->id, 'customer_id' => $customer->id]);

    Livewire::test(JourneySessionsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])->assertCanSeeTableRecords([$session]);
});
