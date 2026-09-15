<?php

namespace App\Models;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Enums\VideoTestimonialSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPagePlacements;
use App\Models\Concerns\HasPublicId;
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
])]
class VideoTestimonial extends Model
{
    /** @use HasFactory<VideoTestimonialFactory> */
    use Auditable, HasFactory, HasPagePlacements, HasPublicId, Publishable, SoftDeletes;

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
     * The 11-character video id from any common YouTube URL shape (watch,
     * youtu.be, shorts, embed, live). Only this validated id is ever put into
     * an embed or thumbnail URL, never the admin's raw input.
     */
    public static function youtubeIdFrom(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $pattern = '~^(?:https?://)?(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~i';

        return preg_match($pattern, trim($url), $matches) === 1 ? $matches[1] : null;
    }

    public function youtubeId(): ?string
    {
        return $this->video_source === VideoTestimonialSource::YouTube ? self::youtubeIdFrom($this->youtube_url) : null;
    }

    public function videoUrl(): ?string
    {
        return $this->video_source === VideoTestimonialSource::Upload && $this->video_path
            ? Storage::disk('public')->url($this->video_path)
            : null;
    }

    public function embedUrl(): ?string
    {
        $id = $this->youtubeId();

        return $id ? "https://www.youtube-nocookie.com/embed/{$id}?autoplay=1&rel=0&playsinline=1" : null;
    }

    /**
     * The uploaded cover image, else YouTube's own thumbnail. An uploaded
     * video with no cover returns null and the card shows the video's first
     * frame instead.
     */
    public function posterUrl(): ?string
    {
        if ($this->poster_path) {
            return Storage::disk('public')->url($this->poster_path);
        }

        $id = $this->youtubeId();

        return $id ? "https://i.ytimg.com/vi/{$id}/hqdefault.jpg" : null;
    }

    /**
     * The customer's own portrait, shown on their video card. Null means the
     * card falls back to the customer's initial.
     */
    public function customerPhotoUrl(): ?string
    {
        return $this->customer_photo_path ? Storage::disk('public')->url($this->customer_photo_path) : null;
    }

    public function isPlayable(): bool
    {
        return $this->videoUrl() !== null || $this->embedUrl() !== null;
    }

    /**
     * What the shared player modal needs to open this video. YouTube Shorts
     * are portrait, so the player sizes their frame 9:16 instead of 16:9.
     *
     * @return array{type: string, src: string|null, title: string, portrait: bool}
     */
    public function playerData(): array
    {
        return [
            'type' => $this->video_source->value,
            'src' => $this->videoUrl() ?? $this->embedUrl(),
            'title' => $this->headline ?: "{$this->customer_name}'s story",
            'portrait' => $this->youtubeId() !== null && str_contains((string) $this->youtube_url, '/shorts/'),
        ];
    }
}
