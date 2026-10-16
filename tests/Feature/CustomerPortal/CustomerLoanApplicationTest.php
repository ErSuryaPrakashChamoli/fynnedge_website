<?php

use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Enums\FieldType;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneyDefinition;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Journey\Models\JourneyStep;
use App\Modules\Journey\Models\JourneyStepField;

/**
 * A published loan product whose live journey starts with the identity step
 * every product shares (full_name, phone, email, date_of_birth), then asks a
 * loan-specific question.
 */
function portalLoanProduct(string $slug = 'personal-loan'): LoanProduct
{
    $product = LoanProduct::factory()->published()->create(['slug' => $slug, 'name' => ucwords(str_replace('-', ' ', $slug))]);
    $definition = JourneyDefinition::create(['loan_product_id' => $product->id, 'version' => 1, 'status' => JourneyDefinitionStatus::Active]);
    $basic = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'basic', 'title' => 'Basic details', 'order' => 1]);
    $loan = JourneyStep::create(['journey_definition_id' => $definition->id, 'key' => 'loan', 'title' => 'Loan details', 'order' => 2]);

    foreach ([
        'full_name' => FieldType::Text, 'phone' => FieldType::Tel, 'email' => FieldType::Email, 'date_of_birth' => FieldType::Date,
    ] as $order => $type) {
        JourneyStepField::create(['journey_step_id' => $basic->id, 'key' => $order, 'label' => ucfirst(str_replace('_', ' ', $order)), 'type' => $type, 'validation_rules' => ['required'], 'order' => 1]);
    }

    JourneyStepField::create(['journey_step_id' => $loan->id, 'key' => 'loan_amount', 'label' => 'Loan amount', 'type' => FieldType::Number, 'validation_rules' => ['required', 'numeric', 'min:10000'], 'order' => 1]);

    return $product;
}

beforeEach(function () {
    $this->customer = Customer::factory()->portalAccount('9000000001')->create([
        'full_name' => 'Asha Rao',
        'email' => 'asha@example.test',
        'date_of_birth' => '1990-01-01',
    ]);
});

it('offers only published products with a live application journey', function () {
    $open = portalLoanProduct('personal-loan');
    $noJourney = LoanProduct::factory()->published()->create(['name' => 'Gold Loan Without Journey']);
    $draft = LoanProduct::factory()->create(['name' => 'Unpublished Loan']);

    // By the apply forms' URLs: the header's loan menu names every published product.
    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.apply'))
        ->assertOk()
        ->assertSee(route('portal.apply.start', $open))
        ->assertDontSee(route('portal.apply.start', $noJourney))
        ->assertDontSee(route('portal.apply.start', $draft));

    $this->post(route('portal.apply.start', $noJourney))->assertNotFound();
});

it('starts the existing journey for the customer, with their verified mobile already proved', function () {
    $product = portalLoanProduct();

    $response = $this->actingAs($this->customer, 'customer')->post(route('portal.apply.start', $product));

    $session = JourneySession::query()->sole();
    $response->assertRedirect(route('journey.show', $session));
    expect($session)
        ->customer_id->toBe($this->customer->id)
        ->phone_number->toBe('9000000001')
        ->phone_verified_at->not->toBeNull()
        ->status->toBe(JourneySessionStatus::InProgress);
});

it('resumes the unfinished application instead of starting a second one', function () {
    $product = portalLoanProduct();
    $this->actingAs($this->customer, 'customer');

    $this->post(route('portal.apply.start', $product));
    $this->post(route('portal.apply.start', $product));

    expect(JourneySession::query()->count())->toBe(1);
    $this->get(route('portal.apply'))->assertSee('Continue application');
});

it('prefills the first step from the profile, says what is verified, and saves nothing until submitted', function () {
    $product = portalLoanProduct();
    $this->actingAs($this->customer, 'customer')->post(route('portal.apply.start', $product));
    $session = JourneySession::query()->sole();

    $html = $this->get(route('journey.show', $session))
        ->assertOk()
        ->assertSee('Filled in from your FynnOn by Markedge Technologies profile')
        ->assertSee(['value="Asha Rao"', 'value="asha@example.test"'], false)
        ->getContent();

    $notice = str($html)->after('data-prefill-notice')->before('</ul>')->squish();
    expect((string) $notice)
        ->toMatch('/Phone <span[^>]*> · Verified/')
        ->toMatch('/Full name <span[^>]*> · Not verified/')
        ->toMatch('/Email <span[^>]*> · Not verified/');

    expect($session->responses()->count())->toBe(0);
});

it('asks for loan details it does not have, and validates them as the journey always has', function () {
    $product = portalLoanProduct();
    $this->actingAs($this->customer, 'customer')->post(route('portal.apply.start', $product));
    $session = JourneySession::query()->sole();

    $this->post(route('journey.update', $session), ['full_name' => 'Asha Rao', 'phone' => '9000000001', 'email' => 'asha@example.test', 'date_of_birth' => '1990-01-01'])
        ->assertRedirect(route('journey.show', $session));

    $this->get(route('journey.show', $session))->assertOk()->assertSee('Loan amount')->assertDontSee('Filled in from your');
    $this->post(route('journey.update', $session), ['loan_amount' => 500])->assertSessionHasErrors('loan_amount');
});

it('never prefills someone else\'s session', function () {
    $product = portalLoanProduct();
    $this->get(route('loans.apply', $product));
    $anonymous = JourneySession::query()->sole();

    $this->actingAs($this->customer, 'customer')
        ->get(route('journey.show', $anonymous))
        ->assertOk()
        ->assertDontSee('Filled in from your')
        ->assertDontSee('value="Asha Rao"', false);
});

it('keeps a portal application on its owner when a different email is typed, and keeps the account\'s details', function () {
    $other = Customer::factory()->create(['email' => 'other@example.test', 'full_name' => 'Somebody Else']);
    $product = portalLoanProduct();
    $this->actingAs($this->customer, 'customer')->post(route('portal.apply.start', $product));
    $session = JourneySession::query()->sole();

    $this->post(route('journey.update', $session), ['full_name' => 'Asha Rao', 'phone' => '9000000001', 'email' => 'other@example.test', 'date_of_birth' => '1990-01-01']);

    expect($session->fresh()->customer_id)->toBe($this->customer->id)
        ->and($other->fresh()->full_name)->toBe('Somebody Else')
        ->and($this->customer->fresh()->email)->toBe('asha@example.test');
});

it('never lets an anonymous journey rewrite a portal account found by its email', function () {
    $product = portalLoanProduct();
    $this->get(route('loans.apply', $product));
    $session = JourneySession::query()->sole();
    $session->update(['phone_number' => '9888877777', 'phone_verified_at' => now()]);

    $this->post(route('journey.update', $session), ['full_name' => 'Intruder', 'phone' => '9888877777', 'email' => 'asha@example.test', 'date_of_birth' => '1990-01-01']);

    expect($this->customer->fresh())->full_name->toBe('Asha Rao')->phone->toBe('9000000001');

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.applications'))
        ->assertOk()
        ->assertDontSee(route('journey.show', $session));
});

it('lists the customer\'s own drafts and applications with their status and pending documents', function () {
    $product = portalLoanProduct();
    $this->actingAs($this->customer, 'customer')->post(route('portal.apply.start', $product));
    $draft = JourneySession::query()->sole();

    $ownSession = JourneySession::factory()->create(['customer_id' => $this->customer->id, 'phone_number' => '9000000001', 'phone_verified_at' => now(), 'status' => JourneySessionStatus::Completed]);
    $own = Application::factory()->create([
        'journey_session_id' => $ownSession->id,
        'customer_id' => $this->customer->id,
        'lender_product_id' => LenderProduct::factory()->create()->id,
        'status' => ApplicationStatus::DocumentsPending,
    ]);
    $strangerSession = JourneySession::factory()->create(['phone_number' => '9000000002', 'phone_verified_at' => now()]);
    $stranger = Application::factory()->create(['journey_session_id' => $strangerSession->id, 'lender_product_id' => LenderProduct::factory()->create()->id]);

    $this->get(route('portal.applications'))
        ->assertOk()
        ->assertSee(route('journey.show', $draft))
        ->assertSee(route('applications.show', $own))
        ->assertSee('Documents pending')
        ->assertDontSee(route('applications.show', $stranger));

    $this->get(route('portal.dashboard'))->assertOk()->assertSee(route('applications.show', $own));
});

it('does not show an application merely linked to the customer record by a typed email', function () {
    $session = JourneySession::factory()->create(['customer_id' => $this->customer->id, 'phone_number' => '9000000002', 'phone_verified_at' => now()]);
    $application = Application::factory()->create(['journey_session_id' => $session->id, 'customer_id' => $this->customer->id, 'lender_product_id' => LenderProduct::factory()->create()->id]);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.applications'))
        ->assertOk()
        ->assertDontSee(route('applications.show', $application));
});
