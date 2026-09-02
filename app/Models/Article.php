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

#[Fillable(['title', 'slug', 'excerpt', 'body', 'category', 'status', 'published_at', 'expires_at'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable, Seoable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'category' => LoanCategory::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
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
