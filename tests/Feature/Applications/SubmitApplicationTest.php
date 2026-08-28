<?php

use App\Modules\Applications\Actions\SubmitApplication;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;

it('refuses to submit when required documents are missing', function () {
    $application = Application::factory()->create();
    $documentType = DocumentType::factory()->create();
    LenderProductDocumentRequirement::create([
        'lender_product_id' => $application->lender_product_id,
        'document_type_id' => $documentType->id,
        'is_required' => true,
    ]);

    $errors = app(SubmitApplication::class)->handle($application);

    expect($errors)->not->toBeEmpty();
    expect($application->fresh()->status)->not->toBe(ApplicationStatus::Submitted);
});

it('submits once every required document is uploaded', function () {
    $application = Application::factory()->create();
    $documentType = DocumentType::factory()->create();
    LenderProductDocumentRequirement::create([
        'lender_product_id' => $application->lender_product_id,
        'document_type_id' => $documentType->id,
        'is_required' => true,
    ]);
    ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $documentType->id,
    ]);

    $errors = app(SubmitApplication::class)->handle($application);

    expect($errors)->toBeEmpty();
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
    expect($application->fresh()->submitted_at)->not->toBeNull();
});

it('ignores optional documents when checking submission readiness', function () {
    $application = Application::factory()->create();
    $optionalType = DocumentType::factory()->create();
    LenderProductDocumentRequirement::create([
        'lender_product_id' => $application->lender_product_id,
        'document_type_id' => $optionalType->id,
        'is_required' => false,
    ]);

    $errors = app(SubmitApplication::class)->handle($application);

    expect($errors)->toBeEmpty();
});
