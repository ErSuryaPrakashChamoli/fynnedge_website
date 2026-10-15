<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use Database\Factories\JobOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'sort_order', 'status', 'title_hi'])]
class JobOpening extends Model
{
    /** @use HasFactory<JobOpeningFactory> */
    use HasFactory, HasPublicId, HasTranslations;

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

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'title',
        ];
    }
}
