<?php

use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Applications\Actions\UploadApplicationDocument;
use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\DocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('stores an uploaded document against the application', function () {
    $application = Application::factory()->create();
    $documentType = DocumentType::factory()->create();
    $file = UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf');

    $document = app(UploadApplicationDocument::class)->handle($application, $documentType, $file);

    expect($document->application_id)->toBe($application->id);
    expect($document->document_type_id)->toBe($documentType->id);
    expect($document->status)->toBe(DocumentStatus::Uploaded);
    expect($document->original_filename)->toBe('pan.pdf');
    Storage::disk('local')->assertExists($document->path);
    expect(AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::DocumentUploaded)->count())->toBe(1);
});

it('replaces the previous file when re-uploading against the same document type', function () {
    $application = Application::factory()->create();
    $documentType = DocumentType::factory()->create();
    $action = app(UploadApplicationDocument::class);

    $first = $action->handle($application, $documentType, UploadedFile::fake()->create('first.pdf', 50, 'application/pdf'));
    $firstPath = $first->path;

    $second = $action->handle($application, $documentType, UploadedFile::fake()->create('second.pdf', 50, 'application/pdf'));

    expect($application->documents()->count())->toBe(1);
    expect($second->id)->toBe($first->id);
    expect($second->original_filename)->toBe('second.pdf');
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($second->path);
});

it('clears a prior rejection when a document is re-uploaded', function () {
    $application = Application::factory()->create();
    $documentType = DocumentType::factory()->create();
    $action = app(UploadApplicationDocument::class);

    $document = $action->handle($application, $documentType, UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'));
    $document->update(['status' => DocumentStatus::Rejected, 'rejection_reason' => 'Blurry scan']);

    $reuploaded = $action->handle($application, $documentType, UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'));

    expect($reuploaded->status)->toBe(DocumentStatus::Uploaded);
    expect($reuploaded->rejection_reason)->toBeNull();
});
