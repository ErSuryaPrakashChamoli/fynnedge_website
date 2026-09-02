<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Database\Factories\HowItWorksStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The homepage's "How it works" steps — a focused model rather than
 * MarketingSection, since this is a genuinely different shape (an ordered
 * list of short title+description steps, no CTA). Kept intentionally simple
 * per 5.3: no Publishable/scheduling, matching the same "just a draft/publish
 * toggle" pattern as Faq/Banner/CompanyPhoto, since scheduling ahead of time
 * for a handful of static process steps isn't a genuine business need.
 */
#[Fillable(['title', 'description', 'icon_path', 'sort_order', 'status'])]
class HowItWorksStep extends Model
{
    /** @use HasFactory<HowItWorksStepFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }

    public function iconUrl(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }
}
