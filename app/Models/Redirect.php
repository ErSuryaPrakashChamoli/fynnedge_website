<?php

namespace App\Models;

use App\Enums\ContentCacheSection;
use App\Models\Concerns\Auditable;
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
 *
 * A row with status 410 is a "Gone" rule rather than a redirect: the old URL's
 * content was removed for good and nothing equivalent exists, so it answers
 * 410 with no destination. It lives in this same table so one admin screen,
 * one cache and one normalised lookup cover every rule an old URL can have.
 */
#[Fillable(['source_path', 'destination', 'status_code', 'is_active', 'notes'])]
class Redirect extends Model
{
    use Auditable, HasFactory, InvalidatesContentCache;

    public const PERMANENT = 301;

    public const TEMPORARY = 302;

    public const GONE = 410;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_code' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Usage counters change on every hit (written without model events) and
     * say nothing about who changed the rule, so they stay out of History.
     *
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['hits', 'last_used_at'];
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
     * @return array{destination: string|null, status_code: int}|null
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
     * the chain makes the whole trip temporary. A chain that reaches a Gone
     * row is Gone itself (410, no destination): sending the visitor on to a
     * page that only answers 410 would be a redirect to an error. Null when no
     * row matches, or when the rows lead back to a path already visited (a
     * loop), so the caller serves the page instead of bouncing the browser.
     *
     * @return array{destination: string|null, status_code: int}|null
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

        // A full URL (another site, or this one by absolute URL) ends the walk, as does a Gone row.
        while ($hop['status_code'] !== self::GONE && str_starts_with((string) $hop['destination'], '/')) {
            $next = self::normalizePath($hop['destination']);

            if (isset($visited[$next])) {
                return null;
            }

            if (! isset($map[$next])) {
                break;
            }

            $visited[$next] = true;
            $hop = $map[$next];

            if ($hop['status_code'] !== self::PERMANENT) {
                $statusCode = $hop['status_code'];
            }
        }

        if ($hop['status_code'] === self::GONE) {
            return ['destination' => null, 'status_code' => self::GONE];
        }

        return ['destination' => $hop['destination'], 'status_code' => $statusCode];
    }

    /**
     * @return array<string, array{destination: string|null, status_code: int}>
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
