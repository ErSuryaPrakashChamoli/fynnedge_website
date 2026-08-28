<?php

namespace Database\Factories;

use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\Application;
use App\Modules\Applications\Models\ApplicationDocument;
use App\Modules\Applications\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    protected $model = ApplicationDocument::class;

    public function definition(): array
    {
        return [
            'public_id' => Str::uuid(),
            'application_id' => Application::factory(),
            'document_type_id' => DocumentType::factory(),
            'disk' => 'local',
            'path' => 'applications/test/'.Str::random(10).'.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 102400,
            'status' => DocumentStatus::Uploaded,
            'rejection_reason' => null,
            'uploaded_at' => now(),
            'verified_at' => null,
        ];
    }
}
