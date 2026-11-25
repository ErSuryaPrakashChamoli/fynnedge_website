<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Someone who writes FynnEdge articles. Their name, photo and designation are
 * the byline on their articles, with an "About the author" box and a public
 * page at /resources/authors/{slug}.
 *
 * Deactivating (is_active = false) hides the author from the website but
 * keeps every article linked; deleting sets those articles' author to none.
 */
#[Fillable(['name', 'slug', 'designation', 'bio', 'photo_path', 'profile_url', 'is_active', 'sort_order', 'name_hi', 'designation_hi', 'bio_hi'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory, HasPublicId, HasTranslations;

    public const PHOTO_DIRECTORY = 'authors';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translatableAttributes(): array
    {
        return ['name', 'designation', 'bio'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function photoUrl(): ?string
    {
        return filled($this->photo_path) ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * Up to two initials, shown in place of a missing photo.
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->getRawOriginal('name', $this->name))) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
