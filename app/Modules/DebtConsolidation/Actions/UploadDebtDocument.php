<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores a document for a case: encrypted at rest on the private disk, with
 * its SHA-256, linked to the case (and optionally a liability or closure).
 * Uploading a replacement creates a new version that supersedes the old one;
 * the old file is kept. Files are only ever read by people — nothing extracts
 * data from them.
 *
 * Validation (type, size) happens in the caller's request rules from
 * config('debt_consolidation.documents'); this action re-checks the type by
 * content, not by the browser's claim.
 */
class UploadDebtDocument
{
    public const DISK = 'local';

    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function handle(
        DebtApplication $application,
        UploadedFile $file,
        ?DocumentType $documentType,
        ActorType $uploadedBy,
        ?int $userId = null,
        ?string $label = null,
        ?int $liabilityId = null,
        ?int $closureId = null,
        ?DebtDocument $replaces = null,
    ): DebtDocument {
        $bytes = (string) file_get_contents($file->getRealPath());
        // Sniffed from the bytes themselves: the browser's claim and the file
        // extension are both under the uploader's control.
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['file' => __('Only PDF, JPG and PNG files can be uploaded.')]);
        }

        if ($replaces !== null && $replaces->debt_application_id !== $application->getKey()) {
            throw ValidationException::withMessages(['file' => __('That document belongs to another case.')]);
        }

        foreach (['debt_liability_id' => $liabilityId] as $column => $id) {
            if ($id !== null && ! $application->liabilities()->whereKey($id)->exists()) {
                throw ValidationException::withMessages(['file' => __('That loan belongs to another case.')]);
            }
        }

        if ($closureId !== null && ! $application->closures()->whereKey($closureId)->exists()) {
            throw ValidationException::withMessages(['file' => __('That closure belongs to another case.')]);
        }

        $path = 'debt-consolidation/'.$application->public_id.'/'.Str::uuid().'.enc';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString($bytes));

        return DB::transaction(function () use ($application, $file, $documentType, $uploadedBy, $userId, $label, $liabilityId, $closureId, $replaces, $bytes, $path, $mime): DebtDocument {
            $document = $application->documents()->create([
                'debt_liability_id' => $liabilityId ?? $replaces?->debt_liability_id,
                'debt_closure_id' => $closureId ?? $replaces?->debt_closure_id,
                'document_type_id' => $documentType?->getKey() ?? $replaces?->document_type_id,
                'label' => $label !== null ? mb_substr(trim($label), 0, 150) : $replaces?->label,
                'disk' => self::DISK,
                'path' => $path,
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $mime,
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'is_encrypted' => true,
                'version' => $replaces === null ? 1 : $replaces->version + 1,
                'supersedes_id' => $replaces?->getKey(),
                'status' => DocumentStatus::Uploaded,
                'uploaded_by_type' => $uploadedBy,
                'uploaded_by' => $userId,
            ]);

            $application->recordEvent('document_uploaded', ['document' => $document->public_id, 'version' => $document->version, 'by' => $uploadedBy->value], $userId);

            return $document;
        });
    }
}
