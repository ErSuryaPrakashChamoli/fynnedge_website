<?php

namespace App\Modules\Journey\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\LoanProduct;
use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use Database\Factories\JourneyDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['loan_product_id', 'version', 'status'])]
class JourneyDefinition extends Model
{
    /** @use HasFactory<JourneyDefinitionFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): JourneyDefinitionFactory
    {
        return JourneyDefinitionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => JourneyDefinitionStatus::class,
        ];
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(JourneyStep::class)->orderBy('order');
    }
}
