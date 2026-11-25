<?php

use App\Enums\EnquiryType;
use App\Filament\Pages\LeadInbox as LeadInboxPage;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Models\User;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\Leads\Services\LeadInbox;
use App\Modules\Leads\Services\LeadsCsv;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('lists every lead source in one read-only view with its page, campaign and verification', function () {
    $product = LoanProduct::factory()->create();
    $enquiry = ContactEnquiry::factory()->forLoanProduct($product)->create(['phone' => '9876500001', 'phone_verified_at' => now()]);
    $check = CreditScoreCheck::factory()->create(['mobile_number' => '9876500002']);
    $session = JourneySession::factory()->create([
        'loan_product_id' => $product->id,
        'phone_number' => '9876500003',
        'phone_verified_at' => now(),
        'landing_page' => 'https://fynnedge.test/loans/'.$product->slug.'/apply?utm_source=google&utm_campaign=diwali',
        'utm_source' => 'google',
        'utm_campaign' => 'diwali',
    ]);
    $otp = MobileOtpChallenge::factory()->verified()->create(['mobile_number' => '9876500004']);

    $rows = LeadInbox::unrestricted()->get()->keyBy(fn (LeadInboxEntry $row): string => $row->getKey());

    expect($rows)->toHaveCount(4)
        ->and($rows["enquiry-{$enquiry->id}"]->sourcePath())->toBe('/loans/'.$product->slug)
        ->and($rows["enquiry-{$enquiry->id}"]->verified_at)->not->toBeNull()
        ->and($rows["credit_score_check-{$check->id}"]->source)->toBe(LeadSource::CreditScoreCheck)
        ->and($rows["credit_score_check-{$check->id}"]->source_page)->toBeNull()
        ->and($rows["online_application-{$session->id}"]->utm_campaign)->toBe('diwali')
        ->and($rows["otp_verification-{$otp->id}"]->detailLabel())->toBe(MobileOtpPurpose::LoanEnquiry->getLabel());
});

it('leaves out portal sign-ins and applications nobody gave a number for', function () {
    MobileOtpChallenge::factory()->verified()->forPurpose(MobileOtpPurpose::CustomerLogin)->create();
    JourneySession::factory()->create(['phone_number' => null]);

    expect(LeadInbox::unrestricted()->count())->toBe(0);
});

it('shows a user only the lead sources whose own screens they can open', function () {
    $enquiry = ContactEnquiry::factory()->create();
    $check = CreditScoreCheck::factory()->create();

    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles([]);
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:ContactEnquiry'));

    $this->actingAs($user);

    expect(LeadSource::accessible())->toBe([LeadSource::Enquiry]);

    Livewire::test(LeadInboxPage::class)
        ->assertCanSeeTableRecords(LeadInboxEntry::query()->fromSub(LeadInbox::select(LeadSource::Enquiry), 'lead_inbox')->get())
        ->assertCanNotSeeTableRecords(LeadInboxEntry::query()->fromSub(LeadInbox::select(LeadSource::CreditScoreCheck), 'lead_inbox')->get());
});

it('refuses the inbox to admins who cannot open any lead source', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->syncRoles(['Marketing']);

    $this->actingAs($user)->get(LeadInboxPage::getUrl())->assertForbidden();
});

it('filters the inbox by source and verification', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $verified = ContactEnquiry::factory()->create(['phone_verified_at' => now()]);
    $unverified = ContactEnquiry::factory()->create(['phone_verified_at' => null]);
    $otp = MobileOtpChallenge::factory()->verified()->create();

    $rows = fn (array $keys) => LeadInbox::unrestricted()->get()->filter(fn (LeadInboxEntry $row): bool => in_array($row->getKey(), $keys, true));

    Livewire::test(LeadInboxPage::class)
        ->assertCanSeeTableRecords($rows(["enquiry-{$verified->id}", "enquiry-{$unverified->id}"]))
        ->assertCanNotSeeTableRecords($rows(["otp_verification-{$otp->id}"]))
        ->filterTable('verified', true)
        ->assertCanSeeTableRecords($rows(["enquiry-{$verified->id}"]))
        ->assertCanNotSeeTableRecords($rows(["enquiry-{$unverified->id}"]));
});

it('shows one person\'s journey across sources by exact mobile number only', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    ContactEnquiry::factory()->create(['phone' => '9811100000']);
    CreditScoreCheck::factory()->create(['mobile_number' => '9811100000']);
    ContactEnquiry::factory()->create(['phone' => '9811100001']);

    $journey = Livewire::test(LeadInboxPage::class)->instance()->journeyFor('9811100000');

    expect($journey)->toHaveCount(2)
        ->and($journey->pluck('source')->all())->toEqualCanonicalizing([LeadSource::Enquiry, LeadSource::CreditScoreCheck]);
});

it('exports the inbox as CSV with formula-safe cells', function () {
    ContactEnquiry::factory()->create(['name' => '=HYPERLINK("x")', 'enquiry_type' => EnquiryType::Contact]);

    $row = LeadsCsv::row(LeadInbox::unrestricted()->first());

    expect(LeadsCsv::COLUMNS)->toContain('Source page', 'FynnOn LMS')
        ->and($row[3])->toBe('=HYPERLINK("x")');

    $response = LeadsCsv::download(LeadInbox::unrestricted());

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain("\"'=HYPERLINK(\"\"x\"\")\"");
});
