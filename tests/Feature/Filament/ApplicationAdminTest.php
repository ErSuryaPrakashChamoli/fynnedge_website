<?php

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\RelationManagers\DocumentsRelationManager;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\Applications\Enums\ApplicationStatus;
use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\Applications\Models\LenderProductDocumentRequirement;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('renders the document type index, create, and edit pages', function () {
    $documentType = DocumentType::factory()->create();

    $this->get('/admin/document-types')->assertOk();
    $this->get('/admin/document-types/create')->assertOk();
    $this->get("/admin/document-types/{$documentType->public_id}/edit")->assertOk();
});

it('renders the document requirement index and create pages', function () {
    $requirement = LenderProductDocumentRequirement::create([
        'lender_product_id' => LenderProduct::factory()->create()->id,
        'document_type_id' => DocumentType::factory()->create()->id,
        'is_required' => true,
    ]);

    $this->get('/admin/document-requirements')->assertOk();
    $this->get("/admin/document-requirements/{$requirement->id}/edit")->assertOk();
});

it('renders the loan applications index and edit pages', function () {
    $application = Application::factory()->create();

    $this->get('/admin/applications')->assertOk();
    $this->get("/admin/applications/{$application->public_id}/edit")->assertOk();
});

it('lets an admin verify an uploaded document', function () {
    $application = Application::factory()->create();
    $document = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'status' => DocumentStatus::Uploaded,
    ]);

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => EditApplication::class,
    ])
        ->assertCanSeeTableRecords([$document])
        ->callTableAction('verify', $document);

    expect($document->fresh()->status)->toBe(DocumentStatus::Verified);
    expect($document->fresh()->verified_at)->not->toBeNull();
});

it('lets an admin reject an uploaded document with a reason', function () {
    $application = Application::factory()->create();
    $document = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'status' => DocumentStatus::Uploaded,
    ]);

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => EditApplication::class,
    ])
        ->callTableAction('reject', $document, data: ['rejection_reason' => 'Illegible scan']);

    expect($document->fresh()->status)->toBe(DocumentStatus::Rejected);
    expect($document->fresh()->rejection_reason)->toBe('Illegible scan');
});

it('shows the audit history for an application', function () {
    $application = Application::factory()->create();
    $application->update(['status' => ApplicationStatus::DocumentsSubmitted]);

    Livewire::test(AuditLogsRelationManager::class, [
        'ownerRecord' => $application,
        'pageClass' => EditApplication::class,
    ])
        ->assertCanSeeTableRecords($application->auditLogs);
});
