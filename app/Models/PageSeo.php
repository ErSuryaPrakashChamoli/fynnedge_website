<?php

namespace App\Models;

use App\Models\Concerns\Seoable;
use App\Support\UrlPath;
use Database\Factories\PageSeoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed SEO for ONE public URL, applied by
 * App\Support\Seo\PageSeoOverrides from the base layout.
 *
 * It exists for the pages that have no record of their own to hang SEO off —
 * the homepage, /contact, the calculators, /faqs, /loans — which until now
 * could only ever show the sitewide default meta tags. A record-backed page
 * (Page, Article, LoanProduct, LoanLandingPage) is still better edited through
 * its own SEO section; a row here for that URL wins over it, because the more
 * specific instruction ("this exact URL gets this title") has to be the one
 * that takes effect, or an admin who adds one and sees nothing change has no
 * way to tell which layer won.
 *
 * The values live on the shared seo_metas morph table via Seoable, not on
 * columns of this table, so every field SeoFormSection already offers works
 * here without a second schema to keep in step.
 */
#[Fillable(['url_path', 'is_active'])]
class PageSeo extends Model
{
    /** @use HasFactory<PageSeoFactory> */
    use HasFactory, Seoable;

    public const CACHE_KEY = 'page-seo:active';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The id of the active row for a request path, or null.
     *
     * Only the path → id map is cached, and it is read on every public request;
     * the row itself (with its seo_metas relation) is loaded lazily, so a page
     * with no override costs no query at all.
     *
     * @return array<string, int>
     */
    public static function activeMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()
            ->where('is_active', true)
            ->get(['id', 'url_path'])
            ->mapWithKeys(fn (self $pageSeo): array => [
                self::normalizePath($pageSeo->url_path) => $pageSeo->id,
            ])
            ->all());
    }

    public static function normalizePath(string $path): string
    {
        return UrlPath::normalize($path);
    }

    /**
     * A URL rule has no content of its own, so there is nothing to derive a
     * title or description from the way Seoable does for a Page or an Article —
     * only what an admin typed into the SEO section. Both return an empty
     * string/null rather than falling back, which is what lets the layout keep
     * the page's existing value when the field is left blank.
     */
    public function seoTitle(): string
    {
        return $this->seoMeta?->title ?: '';
    }

    public function seoDescription(): ?string
    {
        return $this->seoMeta?->description ?: null;
    }
}
