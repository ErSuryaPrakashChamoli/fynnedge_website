<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
 * the cache is dropped on any write through the saved/deleted hooks.
 */
#[Fillable(['source_path', 'destination', 'status_code', 'is_active'])]
class Redirect extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'redirects:active';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_code' => 'integer',
            'last_used_at' => 'datetime',
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
     * The active redirect for a request path, or null.
     *
     * @return array{destination: string, status_code: int}|null
     */
    public static function lookup(string $path): ?array
    {
        return self::activeMap()[self::normalizePath($path)] ?? null;
    }

    /**
     * @return array<string, array{destination: string, status_code: int}>
     */
    public static function activeMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()
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
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (str_contains($path, '://')) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = strtok($path, '?') ?: '/';
        $path = '/'.trim($path, '/');

        return strtolower($path);
    }
}
