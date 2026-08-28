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
     * Re-uploading against the same document type replaces the previous file —
     * both on disk and in the database — rather than accumulating duplicates.
     */
    public function handle(Application $application, DocumentType $documentType, UploadedFile $file): ApplicationDocument
    {
        $existing = ApplicationDocument::query()
            ->where('application_id', $application->id)
            ->where('document_type_id', $documentType->id)
            ->first();

        $existing?->deleteStoredFile();

        $path = $file->store("applications/{$application->id}", self::DISK);

        $document = ApplicationDocument::query()->updateOrCreate(
            ['application_id' => $application->id, 'document_type_id' => $documentType->id],
            [
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
