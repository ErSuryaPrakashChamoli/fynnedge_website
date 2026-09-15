<?php

namespace App\Models;

use App\Enums\BannerHorizontalAlignment;
use App\Enums\BannerVerticalAlignment;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use App\Support\Theme\SiteThemeStyles;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['image_path', 'image_alt', 'heading', 'subtitle', 'cta_label', 'cta_url', 'cta_bg_color', 'cta_hover_color', 'cta_text_color', 'content_vertical_align', 'content_horizontal_align', 'content_padding_left', 'content_padding_right', 'sort_order', 'status', 'published_at', 'expires_at'])]
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable;

    /**
     * Upper bound for each side's spacing, as a percentage of the banner's
     * width. Both sides at the maximum still leave 20% for the content.
     */
    public const MAX_CONTENT_PADDING = 40;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'content_vertical_align' => BannerVerticalAlignment::class,
            'content_horizontal_align' => BannerHorizontalAlignment::class,
            'content_padding_left' => 'integer',
            'content_padding_right' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * Inline style for this banner's content block: its own button colours as
     * custom properties (read by the carousel's banner-cta rules, falling back
     * to the site-wide banner Settings) and any side spacing.
     *
     * Every value is re-validated here rather than trusted from the database,
     * because a record restored from history or written outside the form
     * skips the form's own validation.
     */
    public function contentStyle(): string
    {
        $declarations = [];

        $colors = [
            '--slide-cta-bg' => $this->cta_bg_color,
            '--slide-cta-hover' => $this->cta_hover_color,
            '--slide-cta-text' => $this->cta_text_color,
        ];

        foreach ($colors as $property => $value) {
            if ($color = SiteThemeStyles::hexColor($value)) {
                $declarations[] = "{$property}:{$color};";
            }
        }

        $paddings = [
            'padding-left' => $this->content_padding_left,
            'padding-right' => $this->content_padding_right,
        ];

        foreach ($paddings as $property => $value) {
            if ($value !== null) {
                $declarations[] = $property.':'.max(0, min(self::MAX_CONTENT_PADDING, (int) $value)).'%;';
            }
        }

        return implode('', $declarations);
    }
}
