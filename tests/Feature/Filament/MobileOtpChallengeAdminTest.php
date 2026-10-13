<?php

use App\Filament\Resources\MobileOtpChallenges\Pages\ListMobileOtpChallenges;
use App\Models\User;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Modules\MobileOtp\Policies\MobileOtpChallengePolicy;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function otpAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('shows a challenge to an administrator with the number masked and no code material', function () {
    $challenge = MobileOtpChallenge::factory()->verified()->create([
        'mobile_number' => '8800618269',
        'sms_provider_reference' => 'areach-ref-123',
    ]);
    $this->actingAs(otpAdmin());

    $this->get('/admin/mobile-otp-verifications')
        ->assertOk()
        ->assertSee('******8269');

    $this->get("/admin/mobile-otp-verifications/{$challenge->public_id}")
        ->assertSee('******8269')
        ->assertSee('areach-ref-123')
        ->assertSee('Verified')
        ->assertDontSee('8800618269')
        ->assertDontSee($challenge->otp_hash);
});

it('refuses a panel user whose role does not include otp verifications', function () {
    $this->seed(RoleSeeder::class);
    $user = otpAdmin();
    $user->syncRoles(['Marketing']);
    $this->actingAs($user);

    $this->get('/admin/mobile-otp-verifications')->assertForbidden();
});

it('lets a user granted only the view permissions read the log', function () {
    $user = otpAdmin();
    $user->syncRoles([]);
    $user->givePermissionTo([Permission::findOrCreate('ViewAny:MobileOtpChallenge'), Permission::findOrCreate('View:MobileOtpChallenge')]);
    $challenge = MobileOtpChallenge::factory()->create();
    $this->actingAs($user);

    $this->get('/admin/mobile-otp-verifications')->assertOk();
    $this->get("/admin/mobile-otp-verifications/{$challenge->public_id}")->assertOk();
});

it('offers no way to create, edit or delete a challenge, even to a super admin', function () {
    $challenge = MobileOtpChallenge::factory()->create();
    $this->actingAs(otpAdmin());

    $this->get('/admin/mobile-otp-verifications/create')->assertNotFound();
    $this->get("/admin/mobile-otp-verifications/{$challenge->public_id}/edit")->assertNotFound();
    Livewire::test(ListMobileOtpChallenges::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('never authorises writes, whatever permissions a role is given', function () {
    $user = otpAdmin();
    $user->syncRoles([]);
    $user->givePermissionTo(collect(['Create', 'Update', 'Delete', 'DeleteAny'])->map(fn (string $ability) => Permission::findOrCreate("{$ability}:MobileOtpChallenge")));
    $challenge = MobileOtpChallenge::factory()->create();
    $policy = new MobileOtpChallengePolicy;

    expect($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $challenge))->toBeFalse()
        ->and($policy->delete($user, $challenge))->toBeFalse()
        ->and($policy->deleteAny($user))->toBeFalse()
        ->and($policy->viewAny($user))->toBeFalse();
});

it('finds a challenge by the full number although it shows only the last four digits', function () {
    $match = MobileOtpChallenge::factory()->create(['mobile_number' => '8800618269']);
    $other = MobileOtpChallenge::factory()->create(['mobile_number' => '9123456780']);
    $this->actingAs(otpAdmin());

    Livewire::test(ListMobileOtpChallenges::class)
        ->searchTable('8800618269')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

it('filters by the status an admin sees, including derived expiry', function (MobileOtpStatus $status, string $expected) {
    $records = [
        'pending' => MobileOtpChallenge::factory()->create(),
        'expired' => MobileOtpChallenge::factory()->expired()->create(),
        'verified' => MobileOtpChallenge::factory()->verified()->create(),
        'failed' => MobileOtpChallenge::factory()->smsFailed()->create(),
    ];
    $this->actingAs(otpAdmin());

    Livewire::test(ListMobileOtpChallenges::class)
        ->filterTable('status', $status->value)
        ->assertCanSeeTableRecords([$records[$expected]])
        ->assertCanNotSeeTableRecords(collect($records)->except($expected)->values());
})->with([
    [MobileOtpStatus::Pending, 'pending'],
    [MobileOtpStatus::Expired, 'expired'],
    [MobileOtpStatus::Verified, 'verified'],
    [MobileOtpStatus::Failed, 'failed'],
]);

it('filters by purpose and by request date', function () {
    $enquiry = MobileOtpChallenge::factory()->create(['created_at' => '2026-10-01 10:00:00']);
    $application = MobileOtpChallenge::factory()->forPurpose(MobileOtpPurpose::LoanApplication)->create(['created_at' => '2026-10-05 10:00:00']);
    $this->actingAs(otpAdmin());

    Livewire::test(ListMobileOtpChallenges::class)
        ->filterTable('purpose', MobileOtpPurpose::LoanApplication->value)
        ->assertCanSeeTableRecords([$application])
        ->assertCanNotSeeTableRecords([$enquiry]);

    Livewire::test(ListMobileOtpChallenges::class)
        ->filterTable('created_at', ['from' => '2026-09-30', 'until' => '2026-10-02'])
        ->assertCanSeeTableRecords([$enquiry])
        ->assertCanNotSeeTableRecords([$application]);
});

it('labels an accepted sms that was never verified as pending, not verified', function () {
    $acceptedOnly = MobileOtpChallenge::factory()->create();
    $this->actingAs(otpAdmin());

    Livewire::test(ListMobileOtpChallenges::class)
        ->assertTableColumnStateSet('effective_status', MobileOtpStatus::Pending, $acceptedOnly)
        ->assertTableColumnStateSet('sms_status', $acceptedOnly->sms_status, $acceptedOnly);
});
