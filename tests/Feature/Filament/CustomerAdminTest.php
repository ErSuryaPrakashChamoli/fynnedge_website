<?php

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\JourneySessionsRelationManager;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\CreditBureau\Services\CrifRoles;
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

it('adds a customer by hand and opens their page, where the CRIF report is fetched', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/customers/create')->assertOk();

    Livewire::test(CreateCustomer::class)
        ->fillForm(['full_name' => 'Surya Prakash Chamoli', 'email' => 'walk.in@example.test', 'phone' => '+91 90000-00001'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(CustomerResource::getUrl('edit', ['record' => Customer::query()->sole()]));

    expect(Customer::query()->sole())
        ->full_name->toBe('Surya Prakash Chamoli')
        ->phone->toBe('9000000001');
});

it('refuses a second customer with the same email and an invalid mobile number', function () {
    $this->actingAs($this->admin);
    Customer::factory()->create(['email' => 'taken@example.test']);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['full_name' => 'Someone Else', 'email' => 'taken@example.test', 'phone' => '12345'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique', 'phone'])
        ->assertSee('A customer with this email address already exists.');

    expect(Customer::query()->count())->toBe(1);
});

it('requires a name for a customer added by hand', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['email' => 'no.name@example.test'])
        ->call('create')
        ->assertHasFormErrors(['full_name' => 'required']);
});

it('lets a Credit Officer add customers but not staff without customer permissions', function () {
    CrifRoles::grant();
    $officer = tap(User::factory()->create(['is_admin' => true]))->syncRoles([CrifRoles::OFFICER_ROLE]);
    $marketing = tap(User::factory()->create(['is_admin' => true]))->syncRoles(['Marketing']);

    $this->actingAs($officer)->get('/admin/customers/create')->assertOk();
    $this->actingAs($marketing)->get('/admin/customers/create')->assertForbidden();
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
