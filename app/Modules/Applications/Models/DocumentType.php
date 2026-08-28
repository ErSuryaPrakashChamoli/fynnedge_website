<?php

namespace App\Modules\Applications\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'label', 'description', 'order'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): DocumentTypeFactory
    {
        return DocumentTypeFactory::new();
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(LenderProductDocumentRequirement::class);
    }
}
