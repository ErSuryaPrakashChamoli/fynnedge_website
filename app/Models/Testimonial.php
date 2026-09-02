<?php

namespace App\Models;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'customer_name', 'role_location', 'loan_category', 'rating',
    'quote', 'avatar_path', 'avatar_alt', 'sort_order', 'status', 'published_at', 'expires_at',
])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'loan_category' => LoanCategory::class,
            'status' => PublishStatus::class,
            'rating' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Testimonials with no loan_category are general and shown on every loan
     * page; category-specific ones are shown alongside them for that category.
     */
    public function scopeForCategory(Builder $query, LoanCategory $category): void
    {
        $query->where(function (Builder $query) use ($category): void {
            $query->whereNull('loan_category')->orWhere('loan_category', $category);
        });
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }
}
