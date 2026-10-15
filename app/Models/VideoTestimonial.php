<?php

namespace App\Models;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Enums\VideoTestimonialSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPagePlacements;
use App\Models\Concerns\HasPlayableVideo;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use Database\Factories\VideoTestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'customer_name', 'role_location', 'customer_photo_path', 'customer_photo_alt', 'loan_category', 'rating', 'headline', 'quote',
    'video_source', 'video_path', 'youtube_url', 'poster_path', 'poster_alt',
    'placements', 'show_as_floating', 'sort_order', 'status', 'published_at', 'expires_at',
    'customer_name_hi',
    'role_location_hi',
    'customer_photo_alt_hi',
    'headline_hi',
    'quote_hi',
    'poster_alt_hi',
])]
class VideoTestimonial extends Model
{
    /** @use HasFactory<VideoTestimonialFactory> */
    use Auditable, HasFactory, HasPagePlacements, HasPlayableVideo, HasPublicId, HasTranslations, Publishable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'loan_category' => LoanCategory::class,
            'video_source' => VideoTestimonialSource::class,
            'status' => PublishStatus::class,
            'rating' => 'integer',
            'placements' => 'array',
            'show_as_floating' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The customer's own portrait, shown on their video card. Null means the
     * card falls back to the customer's initial.
     */
    public function customerPhotoUrl(): ?string
    {
        return $this->customer_photo_path ? Storage::disk('public')->url($this->customer_photo_path) : null;
    }

    public function playerTitle(): string
    {
        return $this->headline ?: __(":name's story", ['name' => $this->customer_name]);
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'customer_name',
            'role_location',
            'customer_photo_alt',
            'headline',
            'quote',
            'poster_alt',
        ];
    }
}
