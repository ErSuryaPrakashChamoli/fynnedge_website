<?php

use App\Models\LenderProduct;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Enums\AdvisorReviewStatus;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use App\Modules\DebtConsolidation\Models\DebtLiability;
use App\Modules\DebtConsolidation\Models\DebtReport;
use App\Modules\DebtConsolidation\Notifications\DebtCaseNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->customer = Customer::factory()->portalAccount('9811100001')->create();
    $this->case = DebtApplication::factory()->forCustomer($this->customer)->submitted()->create();
    DebtLiability::factory()->for($this->case, 'application')->create(['lender_name' => 'Bank Alpha']);
});

it('shows the customer their own cases and case details', function () {
    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.debt.index'))
        ->assertOk()
        ->assertSee($this->case->reference);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.debt.show', $this->case))
        ->assertOk()
        ->assertSee('Bank Alpha')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('answers 404 for another customer\'s case, document and report', function () {
    $other = Customer::factory()->portalAccount('9811100002')->create();
    $document = DebtDocument::factory()->for($this->case, 'application')->withStoredFile()->create();
    $report = DebtReport::factory()->for($this->case, 'application')->create(['is_customer_visible' => true, 'report_type' => 'portfolio_summary']);

    $this->actingAs($other, 'customer')->get(route('portal.debt.show', $this->case))->assertNotFound();
    $this->actingAs($other, 'customer')->get(route('portal.debt.documents.show', [$this->case, $document->public_id]))->assertNotFound();
    $this->actingAs($other, 'customer')->get(route('portal.debt.reports.show', [$this->case, $report->public_id]))->assertNotFound();
    $this->actingAs($other, 'customer')->post(route('portal.debt.documents.store', $this->case), [])->assertNotFound();
});

it('does not show a case linked to the account but opened from another number', function () {
    $foreign = DebtApplication::factory()->create(['customer_id' => $this->customer->id, 'mobile' => '9000000009']);

    $this->actingAs($this->customer, 'customer')->get(route('portal.debt.show', $foreign))->assertNotFound();
});

it('sends signed-out visitors to the sign-in page', function () {
    $this->get(route('portal.debt.index'))->assertRedirect(route('customer.login'));
});

it('stores an uploaded statement encrypted against the case', function () {
    $type = DocumentType::query()->where('key', 'loan_statement')->sole();

    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.debt.documents.store', $this->case), ['document_type_id' => $type->id, 'file' => UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.4 bank alpha')])
        ->assertRedirect()
        ->assertSessionHas('status');

    $document = $this->case->documents()->sole();
    expect(Crypt::decryptString(Storage::disk('local')->get($document->path)))->toBe('%PDF-1.4 bank alpha');
});

it('refuses executable or oversized uploads', function () {
    $type = DocumentType::query()->where('key', 'loan_statement')->sole();

    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.debt.documents.store', $this->case), ['document_type_id' => $type->id, 'file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('file');
    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.debt.documents.store', $this->case), ['document_type_id' => $type->id, 'file' => UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf')])
        ->assertSessionHasErrors('file');

    expect($this->case->documents()->count())->toBe(0);
});

it('downloads only reports released to the customer', function () {
    $internal = DebtReport::factory()->for($this->case, 'application')->create(['is_customer_visible' => false, 'report_type' => 'advisor_assessment']);
    $released = DebtReport::factory()->for($this->case, 'application')->create(['is_customer_visible' => true]);
    Storage::disk('local')->put($released->path, Crypt::encryptString('%PDF-1.4 report'));

    $this->actingAs($this->customer, 'customer')->get(route('portal.debt.reports.show', [$this->case, $internal->public_id]))->assertNotFound();
    $this->actingAs($this->customer, 'customer')->get(route('portal.debt.reports.show', [$this->case, $released->public_id]))->assertOk()->assertStreamedContent('%PDF-1.4 report');
});

it('records consent for a lender only when the advisor shortlisted it', function () {
    $shortlisted = DebtLenderMatch::factory()->for($this->case, 'application')->create(['advisor_status' => AdvisorReviewStatus::Shortlisted]);
    $pending = DebtLenderMatch::factory()->for($this->case, 'application')->create();

    $this->actingAs($this->customer, 'customer')->post(route('portal.debt.lender-consent', $this->case), ['match' => $pending->public_id, 'agree' => '1'])->assertNotFound();
    $this->actingAs($this->customer, 'customer')->post(route('portal.debt.lender-consent', $this->case), ['match' => $shortlisted->public_id])->assertSessionHasErrors('agree');
    $this->actingAs($this->customer, 'customer')->post(route('portal.debt.lender-consent', $this->case), ['match' => $shortlisted->public_id, 'agree' => '1'])->assertSessionHas('status');

    $consent = $this->case->consents()->sole();
    expect($consent)->purpose->toBe(ConsentPurpose::LenderSharing)->lender_product_id->toBe($shortlisted->lender_product_id)
        ->and($consent->consent_text)->toContain(LenderProduct::query()->find($shortlisted->lender_product_id)->lender->name);
});

it('lets the customer withdraw a consent', function () {
    $consent = $this->case->consents()->create(['purpose' => ConsentPurpose::Contact, 'consent_text' => 'x', 'text_version' => '1', 'text_hash' => hash('sha256', 'x'), 'method' => 'web_checkbox', 'granted_at' => now()]);

    $this->actingAs($this->customer, 'customer')->post(route('portal.debt.consents.withdraw', [$this->case, $consent->public_id]))->assertSessionHas('status');

    expect($consent->fresh()->isActive())->toBeFalse();
});

it('lists case notifications in the customer\'s language and marks them read', function () {
    app(DebtCaseNotifier::class)->statusChanged($this->case, DebtApplicationStatus::LenderMatching);

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.notifications'))
        ->assertOk()
        ->assertSee('Your case status has changed')
        ->assertSee('Lender matching');

    expect($this->customer->unreadNotifications()->count())->toBe(0);
});

it('accepts only the document types the portal offers', function () {
    $sanctionLetter = DocumentType::query()->where('key', 'sanction_letter')->sole();

    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.debt.documents.store', $this->case), ['document_type_id' => $sanctionLetter->id, 'file' => UploadedFile::fake()->createWithContent('letter.pdf', '%PDF-1.4 letter')])
        ->assertSessionHasErrors('document_type_id');

    expect($this->case->documents()->count())->toBe(0);
});

it('takes no uploads on a closed case and no longer offers the form', function () {
    $this->case->forceFill(['status' => DebtApplicationStatus::Completed])->save();
    $statement = DocumentType::query()->where('key', 'loan_statement')->sole();

    $this->actingAs($this->customer, 'customer')
        ->post(route('portal.debt.documents.store', $this->case), ['document_type_id' => $statement->id, 'file' => UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.4 statement')])
        ->assertForbidden();

    $this->actingAs($this->customer, 'customer')
        ->get(route('portal.debt.show', $this->case))
        ->assertOk()
        ->assertDontSee(route('portal.debt.documents.store', $this->case));
});
