<?php

use App\Enums\EnquiryType;
use App\Enums\PublishStatus;
use App\Models\ContactEnquiry;
use App\Models\Page;
use App\Models\Setting;
use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Notifications\DebtCaseStaffAlert;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true]);
    fakeAreachSms();
});

function verifiedApplicationForm(string $type = 'individual'): Testable
{
    $component = Livewire::test('debt-consolidation-application')
        ->call('chooseType', $type)
        ->set('contact.full_name', 'Ravi Kumar')
        ->set('contact.mobile', '9811122233')
        ->set('contact.city', 'Indore');

    if ($type === 'business') {
        $component->set('profile.business_name', 'Kumar Traders');
    }

    return $component->call('submitContact')->set('otpCode', lastTextedOtp())->call('verifyOtp');
}

it('takes an individual from the first question to a submitted, assessed case', function () {
    $component = verifiedApplicationForm()->assertSet('step', 'liabilities');

    $component
        ->set('liabilities.0.lender_name', 'Bank A')
        ->set('liabilities.0.outstanding', '500000')
        ->set('liabilities.0.rate', '18')
        ->set('liabilities.0.months', '36')
        ->call('saveLiabilities')->assertHasNoErrors()->assertSet('step', 'finances')
        ->set('finances.net_monthly_income', '100000')
        ->call('saveFinances')->assertHasNoErrors()->assertSet('step', 'profile')
        ->set('profile.employment_type', 'salaried')
        ->set('profile.date_of_birth', now()->subYears(35)->toDateString())
        ->call('saveProfile')->assertHasNoErrors()->assertSet('step', 'collateral')
        ->call('saveCollateral')->assertSet('step', 'review')
        ->assertSee('Your debt today')
        ->call('continueTo', 'options')->assertSee('Would consolidating help?')
        ->call('continueTo', 'consent')
        ->set('consentAssessment', true)
        ->set('consentContact', true)
        ->call('submit')->assertHasNoErrors()->assertSet('step', 'done');

    $application = DebtApplication::query()->sole();
    expect($application)
        ->status->toBe(DebtApplicationStatus::EnquiryReceived)
        ->submitted_at->not->toBeNull()
        ->and($application->liabilities()->sole())->lender_name->toBe('Bank A')
        ->and((float) $application->financialProfile->net_monthly_income)->toBe(100000.0)
        ->and($application->consents()->pluck('purpose')->all())->toEqualCanonicalizing([ConsentPurpose::Assessment, ConsentPurpose::Contact])
        ->and($application->snapshots()->sole()->kind)->toBe(DebtCalculationSnapshot::KIND_ASSESSMENT)
        ->and(ContactEnquiry::query()->sole())->enquiry_type->toBe(EnquiryType::DebtConsolidation);
});

it('requires business cash flow and details from a business', function () {
    verifiedApplicationForm('business')
        ->set('liabilities.0.outstanding', '1500000')
        ->set('liabilities.0.rate', '22')
        ->call('saveLiabilities')
        ->call('saveFinances')->assertHasErrors(['finances.average_monthly_revenue', 'finances.monthly_operating_expenses'])
        ->set('finances.average_monthly_revenue', '900000')
        ->set('finances.monthly_operating_expenses', '700000')
        ->call('saveFinances')->assertHasNoErrors()
        ->call('saveProfile')->assertHasErrors(['profile.business_constitution', 'profile.business_vintage_months']);

    expect(DebtApplication::query()->sole()->applicant_type)->toBe(ApplicantType::Business)
        ->and(DebtApplication::query()->sole()->financialProfile->business_name)->toBe('Kumar Traders');
});

it('saves loans as they are typed and resumes where the customer left off', function () {
    verifiedApplicationForm()
        ->set('liabilities.0.lender_name', 'Card Co')
        ->set('liabilities.0.outstanding', '120000');

    expect(DebtLiability::query()->sole())->lender_name->toBe('Card Co');

    Livewire::test('debt-consolidation-application')
        ->assertSet('step', 'liabilities')
        ->assertSet('liabilities.0.lender_name', 'Card Co')
        ->assertSet('liabilities.0.outstanding', '120000');
});

it('carries loans entered in the calculator into the case and links the calculator snapshot', function () {
    Page::query()->where('slug', 'debt-consolidation-assessment')->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);

    Livewire::test('debt-consolidation-calculator')
        ->set('liabilities.0.outstanding', '300000')
        ->set('liabilities.0.rate', '16')
        ->set('liabilities.0.months', '24')
        ->call('continueToAssessment')
        ->assertRedirect(route('debt-consolidation.assessment'));

    verifiedApplicationForm();

    $application = DebtApplication::query()->sole();
    expect((float) $application->liabilities()->sole()->outstanding_principal)->toBe(300000.0)
        ->and(DebtCalculationSnapshot::query()->sole()->debt_application_id)->toBe($application->id);
});

it('never lets another signed-in customer act on a case', function () {
    $component = verifiedApplicationForm();
    $intruder = Customer::factory()->portalAccount('9700000001')->create();
    app(LogInCustomer::class)->handle($intruder, 'otp');

    // Even auto-saving a keystroke re-checks ownership.
    $component->set('liabilities.0.outstanding', '1000')->assertForbidden();

    expect(DebtLiability::query()->count())->toBe(0);
});

it('refuses to submit without both consents', function () {
    verifiedApplicationForm()
        ->set('liabilities.0.outstanding', '500000')
        ->set('liabilities.0.rate', '18')
        ->call('saveLiabilities')
        ->call('continueTo', 'consent')
        ->call('submit')
        ->assertHasErrors(['consentAssessment', 'consentContact']);

    expect(DebtApplication::query()->sole()->submitted_at)->toBeNull();
});

/**
 * A verified customer with one loan saved, finances and profile complete.
 */
function completedApplicationForm(): Testable
{
    return verifiedApplicationForm()
        ->set('liabilities.0.outstanding', '500000')->set('liabilities.0.rate', '18')->set('liabilities.0.months', '36')
        ->call('saveLiabilities')
        ->set('finances.net_monthly_income', '100000')->call('saveFinances')
        ->set('profile.employment_type', 'salaried')->set('profile.date_of_birth', now()->subYears(35)->toDateString())->call('saveProfile')
        ->call('saveCollateral')->call('continueTo', 'consent')
        ->set('consentAssessment', true)->set('consentContact', true);
}

it('returns a case the customer updated after an information request to the advisor, and tells them', function () {
    Notification::fake();
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => true, 'staff_notification_email' => 'team@example.com']);
    completedApplicationForm()->call('submit')->assertSet('step', 'done');
    $application = DebtApplication::query()->sole();
    $application->forceFill(['status' => DebtApplicationStatus::InformationPending])->save();

    Livewire::test('debt-consolidation-application')
        ->call('continueTo', 'consent')->set('consentAssessment', true)->set('consentContact', true)
        ->call('submit')->assertHasNoErrors()->assertSet('step', 'done');

    expect($application->fresh()->status)->toBe(DebtApplicationStatus::AssessmentInProgress);
    Notification::assertSentOnDemand(DebtCaseStaffAlert::class, fn (DebtCaseStaffAlert $alert): bool => $alert->subjectLine === 'information received');
});

it('answers a save on a case that can no longer be changed with a message, not an error page', function () {
    $component = verifiedApplicationForm()->set('liabilities.0.outstanding', '500000')->set('liabilities.0.rate', '18')->call('saveLiabilities');
    DebtApplication::query()->sole()->forceFill(['status' => DebtApplicationStatus::AssessmentInProgress, 'submitted_at' => now()])->save();

    $component->set('finances.net_monthly_income', '100000')->call('saveFinances')->assertHasErrors('application');

    expect(DebtApplication::query()->sole()->financialProfile)->toBeNull();
});

it('refuses to submit when steps were skipped or the service was switched off meanwhile', function () {
    verifiedApplicationForm()
        ->set('liabilities.0.outstanding', '500000')->set('liabilities.0.rate', '18')->call('saveLiabilities')
        ->call('continueTo', 'consent')->set('consentAssessment', true)->set('consentContact', true)
        ->call('submit')->assertHasErrors('application')->assertNotSet('step', 'done');

    expect(DebtApplication::query()->sole()->submitted_at)->toBeNull();

    $component = completedApplicationForm();
    Setting::set(DebtConsolidationSettings::KEY, ['enabled' => false]);

    $component->call('submit')->assertHasErrors('application')->assertNotSet('step', 'done');

    expect(DebtApplication::query()->whereNotNull('submitted_at')->count())->toBe(0);
});

it('keeps a business loss as declared', function () {
    verifiedApplicationForm('business')
        ->set('liabilities.0.outstanding', '1500000')->set('liabilities.0.rate', '22')->call('saveLiabilities')
        ->set('finances.average_monthly_revenue', '900000')->set('finances.monthly_operating_expenses', '700000')->set('finances.annual_net_profit', '-500000')
        ->call('saveFinances')->assertHasNoErrors();

    expect((float) DebtApplication::query()->sole()->financialProfile->annual_net_profit)->toBe(-500000.0);
});

it('keeps the calculator figures, income included, with a case the signed-in customer resumes', function () {
    Page::query()->where('slug', 'debt-consolidation-assessment')->update(['status' => PublishStatus::Published, 'published_at' => now()->subMinute()]);
    verifiedApplicationForm()->set('liabilities.0.outstanding', '200000')->set('liabilities.0.rate', '15');
    $case = DebtApplication::query()->sole();

    Livewire::test('debt-consolidation-calculator')
        ->set('liabilities.0.outstanding', '300000')->set('liabilities.0.rate', '16')->set('liabilities.0.months', '24')
        ->set('finances.net_monthly_income', '80000')
        ->call('continueToAssessment');

    Livewire::test('debt-consolidation-application')->assertSet('applicationId', $case->public_id);

    $snapshot = DebtCalculationSnapshot::query()->sole();
    expect($snapshot->debt_application_id)->toBe($case->id)
        ->and($snapshot->inputs['individual_finances']['net_monthly_income'])->toEqual(80000)
        ->and(session('debt_consolidation.prefill'))->toBeNull();
});

it('asks for the date of birth as DD/MM/YYYY and refuses an impossible year or an applicant under 18', function () {
    $component = verifiedApplicationForm()
        ->set('liabilities.0.outstanding', '500000')->set('liabilities.0.rate', '18')->call('saveLiabilities')
        ->set('finances.net_monthly_income', '100000')->call('saveFinances')
        ->assertSet('step', 'profile')
        ->assertSeeHtml('placeholder="DD/MM/YYYY"')
        ->assertDontSeeHtml('type="date"');

    $component->set('profile.employment_type', 'salaried')
        ->set('profile.date_of_birth', '0001-05-02')->call('saveProfile')
        ->assertHasErrors(['profile.date_of_birth' => 'Enter a real date of birth as DD/MM/YYYY.'])
        ->set('profile.date_of_birth', now()->subYears(17)->toDateString())->call('saveProfile')
        ->assertHasErrors(['profile.date_of_birth' => 'You must be at least 18 years old.'])
        ->set('profile.date_of_birth', '1990-05-02')->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSet('step', 'collateral');

    expect(DebtApplication::query()->sole()->financialProfile->date_of_birth->toDateString())->toBe('1990-05-02');
});
