<?php

namespace App\Modules\Applications\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\Applications\Enums\DocumentStatus;
use Database\Factories\ApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'application_id', 'document_type_id', 'disk', 'path', 'original_filename',
    'mime_type', 'size', 'status', 'rejection_reason', 'uploaded_at', 'verified_at',
])]
class ApplicationDocument extends Model
{
    /** @use HasFactory<ApplicationDocumentFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): ApplicationDocumentFactory
    {
        return ApplicationDocumentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function deleteStoredFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
