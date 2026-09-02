<?php

namespace App\Modules\Applications\Actions;

use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Services\AnalyticsEventDispatcher;
use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Applications\Models\DocumentType;
use Illuminate\Http\UploadedFile;

class UploadApplicationDocument
{
    private const DISK = 'local';

    public function __construct(private readonly AnalyticsEventDispatcher $analytics) {}

    /**
     * Re-uploading against the same document type and slot replaces the
     * previous file — both on disk and in the database — rather than
     * accumulating duplicates. A document type with `allow_multiple` gets
     * several independent slots (e.g. 3 payslips, or as many "Other"
     * documents as the customer adds).
     */
    public function handle(Application $application, DocumentType $documentType, UploadedFile $file, int $slot = 0, ?string $customLabel = null): ApplicationDocument
    {
        $existing = ApplicationDocument::query()
            ->where('application_id', $application->id)
            ->where('document_type_id', $documentType->id)
            ->where('slot', $slot)
            ->first();

        $existing?->deleteStoredFile();

        $path = $file->store("applications/{$application->id}", self::DISK);

        $document = ApplicationDocument::query()->updateOrCreate(
            ['application_id' => $application->id, 'document_type_id' => $documentType->id, 'slot' => $slot],
            [
                'custom_label' => $customLabel,
                'disk' => self::DISK,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'status' => DocumentStatus::Uploaded,
                'rejection_reason' => null,
                'uploaded_at' => now(),
                'verified_at' => null,
            ],
        );

        $this->analytics->track(
            AnalyticsEventKey::DocumentUploaded,
            session: $application->journeySession,
            lenderProduct: $application->lenderProduct,
            properties: ['document_type_id' => $documentType->id],
        );

        return $document;
    }
}
