<?php

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerLocation;
use App\Modules\Customers\Services\CustomersCsv;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Spatie\Permission\Models\Permission;

function customersCsvStaff(array $permissions): User
{
    $staff = tap(User::factory()->create(['is_admin' => true]))->syncRoles([]);
    $staff->givePermissionTo(collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission)));

    return $staff;
}

it('gives a bureau column only to staff who may list that bureau\'s requests', function () {
    $staff = customersCsvStaff(['ViewAny:Customer', 'ViewAny:ExperianCreditRequest']);

    $columns = CustomersCsv::columns(CustomersCsv::bureausFor($staff));

    expect($columns)->toContain('Experian')
        ->not->toContain('CIBIL')
        ->not->toContain('CRIF')
        ->not->toContain('Equifax');
});

it('takes a customer\'s location from their full report when the profile has none', function () {
    $customer = Customer::factory()->create();
    ExperianCreditRequest::factory()->for($customer)->successful(experianFixture('report'))->create();

    $location = CustomerLocation::forCustomer($customer, User::factory()->create(['is_admin' => true]));

    expect($location)->toBe('1 Fixture Lane, Test Nagar, TESTPUR, 400001');
});

it('leaves a customer\'s location blank for staff who may not read the report', function () {
    $customer = Customer::factory()->create();
    ExperianCreditRequest::factory()->for($customer)->successful(experianFixture('report'))->create();

    $location = CustomerLocation::forCustomer($customer, customersCsvStaff(['ViewAny:Customer', 'ViewAny:ExperianCreditRequest', 'View:ExperianCreditRequest']));

    expect($location)->toBeNull();
});

it('neutralises spreadsheet formulas in the downloaded cells', function () {
    Customer::factory()->create(['full_name' => '=HYPERLINK("https://evil.example")']);

    ob_start();
    CustomersCsv::download(Customer::query(), null)->sendContent();
    $csv = ob_get_clean();

    expect($csv)
        ->toContain("'=HYPERLINK")
        ->not->toContain("\n=HYPERLINK");
});
