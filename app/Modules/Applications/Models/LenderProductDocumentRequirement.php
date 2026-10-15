<?php

namespace App\Modules\Applications\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\LenderProduct;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lender_product_id', 'document_type_id', 'is_required', 'notes', 'order', 'min_slots', 'notes_hi'])]
class LenderProductDocumentRequirement extends Model
{
    use HasTranslations;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'min_slots' => 'integer',
        ];
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'notes',
        ];
    }
}
