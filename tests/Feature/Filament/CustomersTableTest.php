<?php

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\User;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\Customers\Models\Customer;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

const CUSTOMERS_CSV_HEADER = "\"Full name\",\"Email address\",Phone,Location,\"PAN (masked)\",Portal,Applications,CRIF,Equifax,Experian,CIBIL,\"First seen (IST)\"\n";

it('filters customers who have a request at any of the chosen bureaus', function () {
    $withCibil = CibilCreditRequest::factory()->create()->customer;
    $withCrif = CrifReport::factory()->create()->customer;
    $withNeither = Customer::factory()->create();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCustomers::class)
        ->filterTable('bureau', [BureauName::Cibil, BureauName::Crif]);

    $page->assertCanSeeTableRecords([$withCibil, $withCrif])
        ->assertCanNotSeeTableRecords([$withNeither]);
});

it('neither offers nor applies a bureau whose requests the user may not list', function () {
    $withCibil = CibilCreditRequest::factory()->create()->customer;
    $withNeither = Customer::factory()->create();
    $staff = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
    $staff->givePermissionTo(collect(['ViewAny:Customer', 'ViewAny:ExperianCreditRequest'])
        ->map(fn (string $permission): Permission => Permission::findOrCreate($permission)));
    $this->actingAs($staff);

    $page = Livewire::test(ListCustomers::class)
        ->filterTable('bureau', [BureauName::Cibil]);

    expect(array_keys($page->instance()->getTable()->getFilter('bureau')->getOptions()))->toBe([BureauName::Experian->value]);
    $page->assertCanSeeTableRecords([$withCibil, $withNeither]);
});

it('filters by the first-seen date in India time', function () {
    $lateOnTheTwentieth = Customer::factory()->create(['created_at' => '2026-10-20 18:00:00']);
    $earlyOnTheTwentyFirst = Customer::factory()->create(['created_at' => '2026-10-20 20:00:00']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCustomers::class)
        ->filterTable('created_at', ['from' => '2026-10-21', 'until' => '2026-10-21']);

    $page->assertCanSeeTableRecords([$earlyOnTheTwentyFirst])
        ->assertCanNotSeeTableRecords([$lateOnTheTwentieth]);
});

it('downloads only the customers the filters leave in view', function () {
    $this->travelTo('2026-10-21 04:35:00');
    $customer = Customer::factory()->create([
        'full_name' => 'Asha Test',
        'email' => 'asha@example.com',
        'phone' => '9876500001',
        'pan_masked' => 'XXXXXX234F',
    ]);
    CibilCreditRequest::factory()->for($customer)->successful(score: 761)->create();
    Customer::factory()->create();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCustomers::class)
        ->filterTable('bureau', [BureauName::Cibil])
        ->callAction('downloadCsv');

    $page->assertFileDownloaded(
        'customers-2026-10-21.csv',
        CUSTOMERS_CSV_HEADER
        ."\"Asha Test\",asha@example.com,9876500001,,XXXXXX234F,\"Not activated\",0,\"Not requested\",\"Not requested\",\"Not requested\",\"Success, score 761\",\"2026-10-21 10:05\"\n",
    );
});

it('downloads only the selected customers', function () {
    $this->travelTo('2026-10-21 04:35:00');
    $selected = Customer::factory()->create(['full_name' => 'Ravi Test', 'email' => 'ravi@example.com', 'phone' => '9876500002']);
    Customer::factory()->create();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $page = Livewire::test(ListCustomers::class)
        ->callTableBulkAction('downloadSelected', [$selected]);

    $page->assertFileDownloaded(
        'customers-2026-10-21.csv',
        CUSTOMERS_CSV_HEADER
        ."\"Ravi Test\",ravi@example.com,9876500002,,,\"Not activated\",0,\"Not requested\",\"Not requested\",\"Not requested\",\"Not requested\",\"2026-10-21 10:05\"\n",
    );
});
