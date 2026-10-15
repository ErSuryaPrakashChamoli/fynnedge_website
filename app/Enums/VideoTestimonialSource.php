<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a video testimonial's or social media video's footage comes from: a
 * file uploaded to the public disk, or a YouTube video embedded through
 * youtube-nocookie.com. The values double as the shared player's `type`.
 */
enum VideoTestimonialSource: string implements HasLabel
{
    case Upload = 'upload';
    case YouTube = 'youtube';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upload => 'Upload a video file',
            self::YouTube => 'YouTube link',
        };
    }
}
