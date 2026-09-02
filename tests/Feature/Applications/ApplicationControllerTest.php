<?php

use App\Models\LenderProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Enums\AssistancePreference;
use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use App\Modules\Eligibility\Enums\EligibilityStatus;
use App\Modules\Eligibility\Models\EligibilityResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('walks an eligible result through selecting a lender, uploading documents, and submitting', function () {
    $lenderProduct = LenderProduct::factory()->create();
    $documentType = DocumentType::factory()->create(['label' => 'PAN Card']);
    LenderProductDocumentRequirement::create([
        'lender_product_id' => $lenderProduct->id,
        'document_type_id' => $documentType->id,
        'is_required' => true,
    ]);
    $result = EligibilityResult::factory()->create([
        'lender_product_id' => $lenderProduct->id,
        'status' => EligibilityStatus::Eligible,
    ]);

    $selectResponse = $this->post(route('applications.select', $result));
    $application = Application::query()->firstOrFail();
    $selectResponse->assertRedirect(route('applications.show', $application));
    expect($application->status)->toBe(ApplicationStatus::LenderSelected);

    $choiceResponse = $this->get(route('applications.show', $application));
    $choiceResponse->assertOk()->assertSee('How would you like to continue?');

    $assistanceResponse = $this->post(route('applications.assistance', $application), [
        'assistance_preference' => AssistancePreference::SelfService->value,
    ]);
    $assistanceResponse->assertRedirect(route('applications.show', $application));

    $showResponse = $this->get(route('applications.show', $application));
    $showResponse->assertOk()->assertSee('PAN Card')->assertSee($lenderProduct->lender->name);

    $uploadResponse = $this->post(route('applications.documents.upload', $application), [
        'documents' => [
            $documentType->id => [UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf')],
        ],
    ]);
    $uploadResponse->assertRedirect(route('applications.show', $application));
    expect($application->documents()->where('document_type_id', $documentType->id)->first()->status)->toBe(DocumentStatus::Uploaded);

    $submitResponse = $this->post(route('applications.submit', $application));
    $submitResponse->assertRedirect(route('applications.show', $application));
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('rejects an upload submission with no file actually chosen', function () {
    $lenderProduct = LenderProduct::factory()->create();
    $documentType = DocumentType::factory()->create(['label' => 'PAN Card']);
    LenderProductDocumentRequirement::create([
        'lender_product_id' => $lenderProduct->id,
        'document_type_id' => $documentType->id,
        'is_required' => true,
    ]);
    $application = Application::factory()->create(['lender_product_id' => $lenderProduct->id]);

    $response = $this->post(route('applications.documents.upload', $application), [
        'documents' => [$documentType->id => [null]],
    ]);

    $response->assertSessionHasErrors('documents');
    expect($application->documents()->count())->toBe(0);
});

it('shows the expert-assisted notification after choosing a FynnEdge loan expert', function () {
    $application = Application::factory()->create();

    $assistanceResponse = $this->post(route('applications.assistance', $application), [
        'assistance_preference' => AssistancePreference::ExpertAssisted->value,
    ]);
    $assistanceResponse->assertRedirect(route('applications.show', $application));

    $showResponse = $this->get(route('applications.show', $application));
    $showResponse->assertOk()->assertSee('Your Loan Journey Just Got Easier.', escape: false);
    $showResponse->assertDontSee('Required documents');

    expect($application->fresh()->assistance_preference)->toBe(AssistancePreference::ExpertAssisted);
});

it('rejects an invalid assistance preference', function () {
    $application = Application::factory()->create();

    $response = $this->post(route('applications.assistance', $application), [
        'assistance_preference' => 'not-a-real-option',
    ]);

    $response->assertSessionHasErrors('assistance_preference');
    expect($application->fresh()->assistance_preference)->toBeNull();
});

it('refuses to select a lender for a not-eligible result', function () {
    $result = EligibilityResult::factory()->create(['status' => EligibilityStatus::NotEligible]);

    $response = $this->post(route('applications.select', $result));

    $response->assertRedirect();
    expect(Application::query()->count())->toBe(0);
});

it('lets a customer remove a document of a type that allows multiple uploads', function () {
    $application = Application::factory()->create();
    $documentType = DocumentType::factory()->create(['allow_multiple' => true]);
    $document = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $documentType->id,
    ]);
    Storage::disk('local')->put($document->path, 'contents');

    $response = $this->delete(route('applications.documents.delete', [$application, $document]));

    $response->assertRedirect(route('applications.show', $application));
    expect(ApplicationDocument::query()->find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing($document->path);
});

it('refuses to remove a document of a type that does not allow multiple uploads', function () {
    $application = Application::factory()->create();
    $documentType = DocumentType::factory()->create(['allow_multiple' => false]);
    $document = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $documentType->id,
    ]);

    $response = $this->delete(route('applications.documents.delete', [$application, $document]));

    $response->assertNotFound();
    expect(ApplicationDocument::query()->find($document->id))->not->toBeNull();
});

it('refuses to remove a document that belongs to a different application', function () {
    $application = Application::factory()->create();
    $otherApplication = Application::factory()->create();
    $documentType = DocumentType::factory()->create(['allow_multiple' => true]);
    $document = ApplicationDocument::factory()->create([
        'application_id' => $otherApplication->id,
        'document_type_id' => $documentType->id,
    ]);

    $response = $this->delete(route('applications.documents.delete', [$application, $document]));

    $response->assertNotFound();
    expect(ApplicationDocument::query()->find($document->id))->not->toBeNull();
});

it('blocks submission until every required document is uploaded', function () {
    $lenderProduct = LenderProduct::factory()->create();
    $documentType = DocumentType::factory()->create();
    LenderProductDocumentRequirement::create([
        'lender_product_id' => $lenderProduct->id,
        'document_type_id' => $documentType->id,
        'is_required' => true,
    ]);
    $application = Application::factory()->create(['lender_product_id' => $lenderProduct->id]);

    $this->post(route('applications.submit', $application));

    expect($application->fresh()->status)->not->toBe(ApplicationStatus::Submitted);
});
