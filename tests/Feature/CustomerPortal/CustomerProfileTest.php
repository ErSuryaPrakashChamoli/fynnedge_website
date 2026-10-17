<?php

use App\Models\AuditLog;
use App\Models\LenderProduct;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use App\Support\Localization\SiteLocale;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->customer = Customer::factory()->portalAccount('9000000001')->create(['full_name' => 'Asha Rao', 'email' => 'asha@example.test']);
    $this->customer->setPan('AAAPZ9999Z');
    $this->customer->save();
});

it('shows the customer their own profile with the PAN and mobile masked', function () {
    Customer::factory()->portalAccount('9000000002')->create(['full_name' => 'Somebody Else']);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.profile'))
        ->assertOk()
        ->assertSee(['Asha Rao', 'asha@example.test', 'XXXXXX999Z', '******0001'])
        ->assertDontSee('AAAPZ9999Z')
        ->assertDontSee('9000000001')
        ->assertDontSee('Somebody Else');
});

it('saves the fields a customer may change and records which ones changed, not their values', function () {
    $this->actingAs($this->customer, 'customer')
        ->put(route('portal.profile.update'), [
            'full_name' => 'Asha Rao',
            'email' => 'asha.rao@example.test',
            'address_line1' => '12 MG Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411 001',
        ])
        ->assertRedirect(route('portal.profile'))
        ->assertSessionHas('status', 'Your details have been updated.');

    expect($this->customer->fresh())
        ->email->toBe('asha.rao@example.test')
        ->city->toBe('Pune')
        ->pincode->toBe('411001');

    $event = AuditLog::query()->where('auditable_id', $this->customer->id)->where('action', 'profile_updated')->sole();
    expect($event->changes['fields']['new'])->toBe('email, address_line1, city, state, pincode')
        ->and(json_encode($event->changes))->not->toContain('Pune')->not->toContain('asha.rao');
});

it('validates what the customer enters', function () {
    Customer::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($this->customer, 'customer')
        ->put(route('portal.profile.update'), ['full_name' => '', 'email' => 'taken@example.test', 'pincode' => '12345'])
        ->assertSessionHasErrors([
            'full_name',
            'email' => 'This email address cannot be used. Please use a different one or contact support.',
            'pincode' => 'Enter a valid 6-digit PIN code.',
        ]);
});

it('refuses identity changes once staff have verified the customer', function () {
    $this->customer->forceFill(['identity_verified_at' => now()])->save();

    $this->actingAs($this->customer, 'customer')
        ->put(route('portal.profile.update'), ['full_name' => 'Changed Name', 'date_of_birth' => '1980-01-01'])
        ->assertSessionHasErrors(['full_name' => 'This detail is verified and can only be changed by our team. Please contact support.', 'date_of_birth']);

    expect($this->customer->fresh()->full_name)->toBe('Asha Rao');
});

it('lets a PAN be added once but never changed from the portal', function () {
    $this->actingAs($this->customer, 'customer')
        ->put(route('portal.profile.update'), ['full_name' => 'Asha Rao', 'pan_number' => 'BBBPZ1111Z'])
        ->assertSessionHasErrors('pan_number');
    expect($this->customer->fresh()->pan())->toBe('AAAPZ9999Z');

    $new = Customer::factory()->portalAccount('9000000002')->create(['full_name' => 'New Customer']);
    $this->actingAs($new, 'customer')
        ->put(route('portal.profile.update'), ['full_name' => 'New Customer', 'pan_number' => 'cccpz2222z'])
        ->assertSessionHasNoErrors();
    expect($new->fresh())->pan()->toBe('CCCPZ2222Z')->pan_masked->toBe('XXXXXX222Z');
});

it('ignores fields a customer may not set', function () {
    $this->actingAs($this->customer, 'customer')
        ->put(route('portal.profile.update'), ['full_name' => 'Asha Rao', 'verified_mobile' => '9000000099', 'identity_verified_at' => now()->toDateTimeString(), 'portal_disabled_at' => null]);

    expect($this->customer->fresh())->verified_mobile->toBe('9000000001')->identity_verified_at->toBeNull();
});

it('lists only the customer\'s own documents and serves only their own files', function () {
    Storage::fake('local');
    $type = DocumentType::factory()->create(['label' => 'Salary slip']);
    $lenderProduct = LenderProduct::factory()->create();
    LenderProductDocumentRequirement::create(['lender_product_id' => $lenderProduct->id, 'document_type_id' => DocumentType::factory()->create(['label' => 'Bank statement'])->id, 'is_required' => true]);

    $ownApplication = Application::factory()->create([
        'journey_session_id' => JourneySession::factory()->create(['phone_number' => '9000000001', 'phone_verified_at' => now()])->id,
        'lender_product_id' => $lenderProduct->id,
    ]);
    Storage::disk('local')->put('applications/1/slip.pdf', '%PDF-1.4 own');
    $own = ApplicationDocument::factory()->create(['application_id' => $ownApplication->id, 'document_type_id' => $type->id, 'path' => 'applications/1/slip.pdf', 'original_filename' => 'slip.pdf']);

    $otherApplication = Application::factory()->create([
        'journey_session_id' => JourneySession::factory()->create(['phone_number' => '9000000002', 'phone_verified_at' => now()])->id,
        'lender_product_id' => LenderProduct::factory()->create()->id,
    ]);
    $other = ApplicationDocument::factory()->create(['application_id' => $otherApplication->id, 'document_type_id' => $type->id, 'original_filename' => 'someone-else.pdf']);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.documents'))
        ->assertOk()
        ->assertSee(['Salary slip', 'Still needed', '1 document'])
        ->assertSee(route('portal.documents.show', $own->public_id))
        ->assertDontSee(route('portal.documents.show', $other->public_id));

    $this->get(route('portal.documents.show', $own->public_id))
        ->assertOk()
        ->assertDownload('slip.pdf');

    $this->get(route('portal.documents.show', $other->public_id))->assertNotFound();
});

it('renders every portal page for a signed-in customer', function (string $route) {
    $this->actingAs($this->customer, 'customer')->get(route($route))->assertOk()->assertSee('FynnOn by Markedge Technologies');
})->with(['portal.dashboard', 'portal.profile', 'portal.credit-score', 'portal.reports.index', 'portal.apply', 'portal.applications', 'portal.documents', 'portal.support', 'portal.settings']);

it('keeps every portal page behind sign-in', function (string $route) {
    $this->get(route($route))->assertRedirect(route('customer.login'));
})->with(['portal.dashboard', 'portal.profile', 'portal.credit-score', 'portal.reports.index', 'portal.apply', 'portal.applications', 'portal.documents', 'portal.support', 'portal.settings']);

it('escapes customer-provided text on the portal pages', function () {
    $this->customer->update(['full_name' => '<script>alert(1)</script>', 'city' => '"><img src=x onerror=alert(1)>']);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.profile'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('renders the portal and the sign-in page in Hindi, brand and bureau names in English', function () {
    $this->withUnencryptedCookie(SiteLocale::COOKIE, 'hi');

    $this->get(route('customer.login'))->assertOk()->assertSee(['मौजूदा ग्राहक', 'FynnOn by Markedge Technologies']);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee(['डैशबोर्ड', 'मेरा क्रेडिट स्कोर', 'साइन आउट'])
        ->assertDontSee('Recent credit reports');
});
