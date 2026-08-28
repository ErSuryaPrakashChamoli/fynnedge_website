<?php

namespace App\Modules\Eligibility\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Lender;
use Database\Factories\EmployerCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lender_id', 'key', 'label', 'description', 'order'])]
class EmployerCategory extends Model
{
    /** @use HasFactory<EmployerCategoryFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): EmployerCategoryFactory
    {
        return EmployerCategoryFactory::new();
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }
}
