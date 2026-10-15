<?php

namespace App\Models\Concerns;

use App\Enums\VideoTestimonialSource;
use Illuminate\Support\Facades\Storage;

/**
 * For content that plays in the shared video player modal
 * (x-site.video-testimonial-player): an uploaded file on the public disk, or a
 * YouTube video embedded through youtube-nocookie.com. Expects `video_source`
 * cast to VideoTestimonialSource, plus `video_path`, `youtube_url` and
 * `poster_path` columns.
 */
trait HasPlayableVideo
{
    /**
     * The title the player modal shows under the video.
     */
    abstract public function playerTitle(): string;

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
            'title' => $this->playerTitle(),
            'portrait' => $this->youtubeId() !== null && str_contains((string) $this->youtube_url, '/shorts/'),
        ];
    }
}
