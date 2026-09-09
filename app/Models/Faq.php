<?php

namespace App\Models;

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['question', 'answer', 'sort_order', 'status', 'faqable_type', 'faqable_id', 'placements', 'published_at', 'expires_at'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'placements' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * FAQs pinned to a page by route name. `placements` holds route-name
     * strings, so a single FAQ can appear on several pages at once — see
     * App\Enums\FaqPlacement.
     */
    public function scopeForPlacement(Builder $query, FaqPlacement|string $placement): void
    {
        $query->whereJsonContains('placements', $placement instanceof FaqPlacement ? $placement->value : $placement);
    }

    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }
}
