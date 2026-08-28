<?php

namespace App\Modules\Applications\Models;

use App\Models\LenderProduct;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lender_product_id', 'document_type_id', 'is_required', 'notes', 'order'])]
class LenderProductDocumentRequirement extends Model
{
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
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
}
