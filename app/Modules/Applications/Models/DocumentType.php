<?php

namespace App\Modules\Applications\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'label', 'description', 'order', 'allow_multiple', 'allow_custom_label', 'label_hi', 'description_hi'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, HasPublicId, HasTranslations;

    protected static function newFactory(): DocumentTypeFactory
    {
        return DocumentTypeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'allow_multiple' => 'boolean',
            'allow_custom_label' => 'boolean',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(LenderProductDocumentRequirement::class);
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'label',
            'description',
        ];
    }
}
