<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use Database\Factories\NavigationLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Admin-managed links appended ALONGSIDE the site's existing hardcoded
 * navigation (header mega-menus, footer Company/Legal columns) — never a
 * replacement for it. See .ai/rules for why those stay hardcoded.
 */
#[Fillable(['label', 'url', 'route_name', 'is_external', 'location', 'parent_id', 'sort_order', 'is_active'])]
class NavigationLink extends Model
{
    /** @use HasFactory<NavigationLinkFactory> */
    use Auditable, HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForLocation(Builder $query, string $location): void
    {
        $query->where('location', $location);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->active()->orderBy('sort_order');
    }

    /**
     * Resolves to a real URL only — an internal `route_name` that no longer
     * exists (renamed/removed route) safely disappears instead of producing
     * a broken link or a fatal RouteNotFoundException.
     */
    public function resolvedUrl(): ?string
    {
        if ($this->route_name) {
            return RouteFacade::has($this->route_name) ? route($this->route_name) : null;
        }

        return $this->url;
    }
}
