<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use Database\Factories\CompanyPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['photo_path', 'photo_alt', 'caption', 'sort_order', 'status', 'photo_alt_hi', 'caption_hi'])]
class CompanyPhoto extends Model
{
    /** @use HasFactory<CompanyPhotoFactory> */
    use Auditable, HasFactory, HasPublicId, HasTranslations;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'photo_alt',
            'caption',
        ];
    }
}
