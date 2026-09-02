<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\JobOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'sort_order', 'status'])]
class JobOpening extends Model
{
    /** @use HasFactory<JobOpeningFactory> */
    use HasFactory, HasPublicId;

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
}
