<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Enums\SocialPlatform;
use App\Enums\VideoTestimonialSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPagePlacements;
use App\Models\Concerns\HasPlayableVideo;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use Database\Factories\SocialVideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A video FynnEdge posted on its own social channels (Instagram, Facebook,
 * LinkedIn, YouTube), re-shown on the website. The footage itself is uploaded
 * here (or linked from YouTube) and plays in the site's own player; the
 * original post is only ever linked to, never embedded, so no social network
 * script or cookie loads on the page.
 */
#[Fillable([
    'platform', 'title', 'tagline', 'caption', 'post_url',
    'video_source', 'video_path', 'youtube_url', 'poster_path', 'cover_time', 'poster_alt',
    'placements', 'sort_order', 'status', 'published_at', 'expires_at',
    'title_hi',
    'tagline_hi',
    'caption_hi',
    'poster_alt_hi',
])]
class SocialVideo extends Model
{
    /** @use HasFactory<SocialVideoFactory> */
    use Auditable, HasFactory, HasPagePlacements, HasPlayableVideo, HasPublicId, HasTranslations, Publishable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'video_source' => VideoTestimonialSource::class,
            'status' => PublishStatus::class,
            'placements' => 'array',
            'cover_time' => 'float',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function playerTitle(): string
    {
        return $this->title;
    }

    /**
     * The moment of an uploaded video the card shows as its cover when no
     * cover image is uploaded: the second an admin picked, else 0.1s (just
     * past a first frame that is often black).
     */
    public function coverFrameTime(): float
    {
        return $this->cover_time > 0 ? $this->cover_time : 0.1;
    }

    /**
     * The short line beside the platform icon on the card: the admin's own
     * words, or the platform's name when they left it blank.
     */
    public function cardLabel(): string
    {
        return filled($this->tagline) ? $this->tagline : $this->platform->getLabel();
    }

    /**
     * The original post, for the card's "View on Instagram" link. Only http(s)
     * links are returned, so a value that slipped past the form can never
     * become a javascript: href.
     */
    public function postUrl(): ?string
    {
        return filled($this->post_url) && preg_match('#^https?://#i', $this->post_url) === 1 ? $this->post_url : null;
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'title',
            'tagline',
            'caption',
            'poster_alt',
        ];
    }
}
