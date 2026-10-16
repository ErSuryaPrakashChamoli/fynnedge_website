<?php

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\RelationManagers\CreditScoreChecksRelationManager;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\AuditLog;
use App\Models\User;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->customer = Customer::factory()->portalAccount('9000000001')->create(['full_name' => 'Asha Rao', 'email' => null]);
    $this->customer->setPan('AAAPZ9999Z');
    $this->customer->save();
});

it('shows staff the portal account with the mobile and PAN masked', function () {
    $this->actingAs($this->admin)
        ->get("/admin/customers/{$this->customer->public_id}/edit")
        ->assertOk()
        ->assertSee(['Customer portal account', 'Active', '******0001', 'XXXXXX999Z'])
        ->assertDontSee('AAAPZ9999Z');
});

it('saves a portal customer who has no email yet', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->fillForm(['full_name' => 'Asha R Rao'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->customer->fresh()->full_name)->toBe('Asha R Rao');
});

it('lets staff disable and re-enable portal sign-in, recording who did it', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->callAction(TestAction::make('disablePortal'));

    expect($this->customer->fresh()->hasPortalAccess())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'portal_disabled')->sole()->user_id)->toBe($this->admin->id);

    Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->callAction(TestAction::make('enablePortal'));

    expect($this->customer->fresh()->hasPortalAccess())->toBeTrue();
});

it('lets staff mark an identity as verified', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
        ->callAction(TestAction::make('verifyIdentity'));

    expect($this->customer->fresh()->isIdentityVerified())->toBeTrue();
});

it('lists the account\'s website checks, demo ones labelled, and its history', function () {
    $this->actingAs($this->admin);
    $live = CreditScoreCheck::factory()->create(['customer_id' => $this->customer->id, 'provider' => CreditScoreCheck::LIVE_PROVIDER]);
    $demo = CreditScoreCheck::factory()->create(['customer_id' => $this->customer->id, 'provider' => 'demo']);
    $this->customer->recordEvent('portal_login', ['via' => 'otp']);

    Livewire::test(CreditScoreChecksRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => EditCustomer::class])
        ->assertCanSeeTableRecords([$live, $demo])
        ->assertSee('Demo (not shown to customer)');

    Livewire::test(AuditLogsRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => EditCustomer::class])
        ->assertCanSeeTableRecords(AuditLog::query()->where('action', 'portal_login')->get());
});

it('filters customers by portal activation', function () {
    $this->actingAs($this->admin);
    $lead = Customer::factory()->create();

    Livewire::test(ListCustomers::class)
        ->filterTable('portal_account', true)
        ->assertCanSeeTableRecords([$this->customer])
        ->assertCanNotSeeTableRecords([$lead]);
});
