<?php

namespace App\Models;

use App\Enums\ContentCacheSection;
use App\Models\Concerns\InvalidatesContentCache;
use App\Support\ContentCache\ContentCache;
use App\Support\UrlPath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One admin-managed URL redirect.
 *
 * Paths are normalised through normalizePath() on the way in AND on the way
 * out (the middleware normalises the incoming request the same way), so
 * "/old-page", "old-page/" and "/old-page?utm_source=x" all resolve to the
 * same row instead of only matching if an admin happened to type the exact
 * form the visitor's browser sends.
 *
 * The whole active set is cached as one array (see lookup()) rather than
 * queried per request: it is small, read on every single public request, and
 * any saved or deleted row invalidates it (InvalidatesContentCache). The hit
 * counter is written with the query builder so a hit never invalidates it.
 */
#[Fillable(['source_path', 'destination', 'status_code', 'is_active'])]
class Redirect extends Model
{
    use HasFactory, InvalidatesContentCache;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_code' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * For writes that skip model events, e.g. a migration's query delete.
     */
    public static function forgetCache(): void
    {
        ContentCache::invalidate(ContentCacheSection::Redirects);
    }

    /**
     * The active redirect for a request path, or null.
     *
     * @return array{destination: string, status_code: int}|null
     */
    public static function lookup(string $path): ?array
    {
        return self::activeMap()[self::normalizePath($path)] ?? null;
    }

    /**
     * Where a request path finally lands, following rows whose destination is
     * itself the source of another active row, so a visitor (and Googlebot) is
     * sent there in ONE hop instead of walking a chain. Rows pile up exactly
     * this way: /a → /b is added, then later /b → /c, and /a is forgotten.
     *
     * The result is permanent only when every hop is: one temporary row in
     * the chain makes the whole trip temporary. Null when no row matches, or
     * when the rows lead back to a path already visited (a loop), so the
     * caller serves the page instead of bouncing the browser.
     *
     * @return array{destination: string, status_code: int}|null
     */
    public static function resolve(string $path): ?array
    {
        $map = self::activeMap();
        $current = self::normalizePath($path);
        $hop = $map[$current] ?? null;

        if (! $hop) {
            return null;
        }

        $statusCode = $hop['status_code'];
        $visited = [$current => true];

        // A full URL (another site, or this one by absolute URL) ends the walk.
        while (str_starts_with($hop['destination'], '/')) {
            $next = self::normalizePath($hop['destination']);

            if (isset($visited[$next])) {
                return null;
            }

            if (! isset($map[$next])) {
                break;
            }

            $visited[$next] = true;
            $hop = $map[$next];

            if ($hop['status_code'] !== 301) {
                $statusCode = $hop['status_code'];
            }
        }

        return ['destination' => $hop['destination'], 'status_code' => $statusCode];
    }

    /**
     * @return array<string, array{destination: string, status_code: int}>
     */
    public static function activeMap(): array
    {
        return ContentCache::remember('redirects:active', [ContentCacheSection::Redirects], fn (): array => self::query()
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (self $redirect): array => [
                self::normalizePath($redirect->source_path) => [
                    'destination' => $redirect->destination,
                    'status_code' => $redirect->status_code,
                ],
            ])
            ->all());
    }

    /**
     * A leading slash, no query string, no trailing slash, lowercased host-less
     * path. "/" itself is preserved — a site can legitimately redirect its own
     * homepage, and trimming it to "" would make every request match.
     *
     * Shared with the per-page SEO overrides through App\Support\UrlPath, so both
     * features treat an admin-typed URL identically.
     */
    public static function normalizePath(string $path): string
    {
        return UrlPath::normalize($path);
    }
}
