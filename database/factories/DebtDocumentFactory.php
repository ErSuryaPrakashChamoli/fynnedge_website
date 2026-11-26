<?php

namespace Database\Factories;

use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<DebtDocument>
 */
class DebtDocumentFactory extends Factory
{
    protected $model = DebtDocument::class;

    public function definition(): array
    {
        return [
            'debt_application_id' => DebtApplication::factory(),
            'label' => 'Loan statement',
            'disk' => 'local',
            'path' => 'debt-consolidation/documents/'.Str::uuid().'.enc',
            'original_filename' => 'statement.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'sha256' => hash('sha256', 'statement'),
            'is_encrypted' => true,
            'version' => 1,
            'status' => DocumentStatus::Uploaded,
            'uploaded_by_type' => ActorType::Customer,
        ];
    }

    /**
     * Writes an encrypted file for the row so downloads can be exercised.
     */
    public function withStoredFile(string $contents = '%PDF-1.4 test'): static
    {
        return $this->afterCreating(function (DebtDocument $document) use ($contents): void {
            Storage::disk($document->disk)->put($document->path, Crypt::encryptString($contents));
            $document->forceFill(['size' => strlen($contents), 'sha256' => hash('sha256', $contents)])->save();
        });
    }
}
