<?php

use App\Models\LenderProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\Application;
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

    $showResponse = $this->get(route('applications.show', $application));
    $showResponse->assertOk()->assertSee('PAN Card');

    $uploadResponse = $this->post(route('applications.documents.upload', $application), [
        'documents' => [
            $documentType->id => UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf'),
        ],
    ]);
    $uploadResponse->assertRedirect(route('applications.show', $application));
    expect($application->documents()->where('document_type_id', $documentType->id)->first()->status)->toBe(DocumentStatus::Uploaded);

    $submitResponse = $this->post(route('applications.submit', $application));
    $submitResponse->assertRedirect(route('applications.show', $application));
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('refuses to select a lender for a not-eligible result', function () {
    $result = EligibilityResult::factory()->create(['status' => EligibilityStatus::NotEligible]);

    $response = $this->post(route('applications.select', $result));

    $response->assertRedirect();
    expect(Application::query()->count())->toBe(0);
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
