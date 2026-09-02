<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use Database\Factories\MarketingSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A single reusable "marketing block" content type — heading/description/
 * image/CTA — identified by a `placement` key that callers (currently just
 * home.blade.php) look up by string, e.g. `MarketingSection::forPlacement('home_finance_cta')`.
 * Each call site is expected to fall back to its own hardcoded default copy
 * when no row exists for that placement, so an empty table never breaks a page.
 */
#[Fillable(['placement', 'heading', 'subheading', 'description', 'image_path', 'image_alt', 'cta_label', 'cta_url', 'sort_order', 'status', 'published_at', 'expires_at'])]
class MarketingSection extends Model
{
    /** @use HasFactory<MarketingSectionFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function forPlacement(string $placement): ?self
    {
        return static::query()->published()->where('placement', $placement)->orderBy('sort_order')->first();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
