<?php

namespace App\Modules\DebtConsolidation\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\DebtConsolidation\Enums\ActorType;
use Database\Factories\DebtDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * A document uploaded for a case (statement, foreclosure letter, NOC,
 * payment proof…). Stored encrypted on the private disk; a new upload of the
 * same document is a new version that supersedes the old one, which is kept.
 * Documents are read by people only — nothing extracts data from them.
 */
#[Fillable([
    'debt_application_id', 'debt_liability_id', 'debt_closure_id', 'document_type_id', 'label', 'disk', 'path',
    'original_filename', 'mime_type', 'size', 'sha256', 'is_encrypted', 'version', 'supersedes_id', 'status',
    'rejection_reason', 'uploaded_by_type', 'uploaded_by', 'verified_by', 'verified_at',
])]
class DebtDocument extends Model
{
    /** @use HasFactory<DebtDocumentFactory> */
    use Auditable, HasFactory, HasPublicId, SoftDeletes;

    protected static function newFactory(): DebtDocumentFactory
    {
        return DebtDocumentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'uploaded_by_type' => ActorType::class,
            'is_encrypted' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['original_filename', 'path'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DebtApplication::class, 'debt_application_id');
    }

    public function liability(): BelongsTo
    {
        return $this->belongsTo(DebtLiability::class, 'debt_liability_id');
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(DebtClosure::class, 'debt_closure_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isCurrent(): bool
    {
        return ! $this->supersededBy()->exists();
    }

    public function title(): string
    {
        return $this->label ?: ($this->documentType?->label ?? __('Document'));
    }

    /**
     * The original file's bytes, decrypted.
     */
    public function contents(): string
    {
        $stored = (string) Storage::disk($this->disk)->get($this->path);

        return $this->is_encrypted ? Crypt::decryptString($stored) : $stored;
    }

    public function downloadName(): string
    {
        $extension = pathinfo($this->original_filename, PATHINFO_EXTENSION) ?: 'pdf';

        return str($this->title())->slug()->append('-v'.$this->version.'.'.strtolower($extension))->toString();
    }
}
