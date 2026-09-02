<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\GrievanceLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['level', 'turnaround_time', 'contact_name', 'designation', 'address', 'phone', 'email', 'sort_order', 'status'])]
class GrievanceLevel extends Model
{
    /** @use HasFactory<GrievanceLevelFactory> */
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
