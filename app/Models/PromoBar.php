<?php

namespace App\Models;

use App\Enums\PromoBarDevice;
use App\Enums\PromoBarTrigger;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPagePlacements;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use App\Support\Theme\SiteThemeStyles;
use Carbon\CarbonInterface;
use Database\Factories\PromoBarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * The sticky offer bar that rises from the bottom of the page once a visitor
 * scrolls, lingers or heads for the exit. Rendered by x-site.promo-bar; which
 * bar a page gets is decided in App\Support\PromoBars\PromoBars.
 */
#[Fillable([
    'name', 'eyebrow', 'headline', 'rotating_messages', 'cta_label', 'cta_url', 'cta_opens_new_tab',
    'image_path', 'image_alt', 'background_color', 'background_color_to', 'text_color', 'highlight_color',
    'cta_bg_color', 'cta_text_color', 'show_countdown', 'placements', 'excluded_placements',
    'trigger', 'trigger_value', 'device', 'reshow_after_hours', 'sort_order', 'status', 'published_at', 'expires_at',
])]
class PromoBar extends Model
{
    /** @use HasFactory<PromoBarFactory> */
    use Auditable, HasFactory, HasPagePlacements, HasPublicId, Publishable, SoftDeletes;

    /**
     * Site-relative paths (never protocol-relative "//host"), http(s) URLs,
     * phone and email links — nothing a browser would run as script.
     */
    public const CTA_URL_PATTERN = '~^(https?://\S+|/(?!/)\S*|tel:\+?[0-9\s-]+|mailto:\S+)$~i';

    protected function casts(): array
    {
        return [
            'rotating_messages' => 'array',
            'placements' => 'array',
            'excluded_placements' => 'array',
            'cta_opens_new_tab' => 'boolean',
            'show_countdown' => 'boolean',
            'trigger' => PromoBarTrigger::class,
            'trigger_value' => 'integer',
            'device' => PromoBarDevice::class,
            'reshow_after_hours' => 'integer',
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function isSafeCtaUrl(mixed $url): bool
    {
        return is_string($url) && preg_match(self::CTA_URL_PATTERN, trim($url)) === 1;
    }

    /**
     * Re-checked on every render rather than trusted from the database: a
     * record restored from history skips the form's validation.
     */
    public function ctaUrl(): ?string
    {
        return self::isSafeCtaUrl($this->cta_url) ? trim($this->cta_url) : null;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * The headline followed by any rotating messages, each ready to echo.
     *
     * @return array<int, HtmlString>
     */
    public function messages(): array
    {
        return collect([$this->headline, ...($this->rotating_messages ?? [])])
            ->filter(fn (mixed $message): bool => is_string($message) && filled($message))
            ->map(fn (string $message): HtmlString => self::emphasise($message))
            ->values()
            ->all();
    }

    /**
     * Escapes the text FIRST, then turns *starred words* into highlighted
     * ones, so an admin can stress "₹50 Lakhs" without ever writing HTML.
     */
    public static function emphasise(string $text): HtmlString
    {
        return new HtmlString((string) preg_replace('/\*([^*]+)\*/', '<strong class="promo-bar-highlight">$1</strong>', e($text)));
    }

    /**
     * Inline custom properties read by the promo bar's CSS, which falls back
     * to the brand navy for any colour left blank. Every value passes the
     * strict hex check again, because this string lands in a style attribute.
     */
    public function barStyle(): string
    {
        $colors = [
            '--promo-bg' => $this->background_color,
            '--promo-bg-to' => $this->background_color_to,
            '--promo-text' => $this->text_color,
            '--promo-highlight' => $this->highlight_color,
            '--promo-cta-bg' => $this->cta_bg_color,
            '--promo-cta-text' => $this->cta_text_color,
        ];

        $declarations = [];

        foreach ($colors as $property => $value) {
            if ($color = SiteThemeStyles::hexColor($value)) {
                $declarations[] = "{$property}:{$color};";
            }
        }

        return implode('', $declarations);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function isExcludedFrom(array $tokens): bool
    {
        return array_intersect($this->excluded_placements ?? [], $tokens) !== [];
    }

    /**
     * True when the bar names one of these pages itself, rather than only
     * reaching it through a site-wide placement.
     *
     * @param  array<int, string>  $tokens
     */
    public function isPinnedToAnyOf(array $tokens): bool
    {
        return array_intersect($this->placements ?? [], $tokens) !== [];
    }

    /**
     * Whether the button leads to the given page on this site — where the bar
     * would only be inviting the visitor to the page they are already on.
     */
    public function linksTo(string $host, string $path): bool
    {
        $url = $this->ctaUrl();

        if ($url === null || ! preg_match('~^(https?://|/)~i', $url)) {
            return false;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);

        if (is_string($urlHost) && strcasecmp($urlHost, $host) !== 0) {
            return false;
        }

        return trim((string) parse_url($url, PHP_URL_PATH), '/') === trim($path, '/');
    }

    public function countdownEndsAt(): ?CarbonInterface
    {
        return $this->show_countdown ? $this->expires_at : null;
    }

    /**
     * Everything the promoBar Alpine component needs, and nothing it doesn't:
     * the copy itself is rendered server-side. The bar can't be closed, so the
     * legacy reshow_after_hours column is deliberately not sent.
     *
     * @return array{id: string, name: string, trigger: string, triggerValue: int, device: string, countdownEndsAt: string|null, messageCount: int}
     */
    public function clientConfig(): array
    {
        $trigger = $this->trigger ?? PromoBarTrigger::Scroll;

        return [
            'id' => (string) $this->public_id,
            'name' => $this->name,
            'trigger' => $trigger->value,
            'triggerValue' => max(0, min($trigger->maxValue(), (int) $this->trigger_value)),
            'device' => ($this->device ?? PromoBarDevice::All)->value,
            'countdownEndsAt' => $this->countdownEndsAt()?->toIso8601String(),
            'messageCount' => count($this->messages()),
        ];
    }
}
