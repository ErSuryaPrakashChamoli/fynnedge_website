<?php

namespace App\Models;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Seoable;
use Database\Factories\LoanProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'slug', 'category', 'summary', 'body', 'features',
    'eligibility_points', 'documents_required', 'process_steps',
    'calculator_key', 'status', 'published_at',
])]
class LoanProduct extends Model
{
    /** @use HasFactory<LoanProductFactory> */
    use HasFactory, HasPublicId, Seoable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => LoanCategory::class,
            'status' => PublishStatus::class,
            'features' => 'array',
            'eligibility_points' => 'array',
            'documents_required' => 'array',
            'process_steps' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function lenderProducts(): HasMany
    {
        return $this->hasMany(LenderProduct::class);
    }

    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }
}
