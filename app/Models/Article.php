<?php

namespace App\Models;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\Seoable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'slug', 'excerpt', 'image_path', 'image_alt', 'body', 'category', 'status', 'show_on_home', 'published_at', 'expires_at'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable, Seoable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'category' => LoanCategory::class,
            'show_on_home' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The optional cover image, shown on the resources card and at the top of
     * the article. Inline images inside the body are separate: the editor
     * uploads those itself and writes their URLs straight into the HTML.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * Newest first for any public listing. published_at is optional in the
     * admin form, and MySQL sorts NULLs last on a DESC order, so an article
     * published without a date would otherwise sink to the bottom of the
     * list — fall back to created_at, with id breaking same-second ties.
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByRaw('coalesce(published_at, created_at) desc')->orderByDesc('id');
    }

    /**
     * The date to show publicly. Mirrors scopeNewestFirst()'s fallback, so
     * a card always displays the date it was actually sorted by instead of
     * rendering with no date at all.
     */
    public function publishedOn(): Carbon
    {
        return $this->published_at ?? $this->created_at ?? Carbon::now();
    }

    /**
     * Articles an admin has left switched on for the home page's "Latest
     * articles" section. Combine with published() and newestFirst().
     */
    public function scopeShownOnHome(Builder $query): void
    {
        $query->where('show_on_home', true);
    }

    /**
     * Prefers articles tagged to this loan category; an untagged article is
     * a "general" resource relevant to every loan type, so it's included as
     * a fallback rather than excluded outright.
     */
    public function scopeForCategoryOrGeneral(Builder $query, LoanCategory $category): void
    {
        $query->where(function (Builder $query) use ($category) {
            $query->where('category', $category)->orWhereNull('category');
        })->orderByRaw('category is null');
    }
}
