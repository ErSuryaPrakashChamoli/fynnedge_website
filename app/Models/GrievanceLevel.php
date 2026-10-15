<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use Database\Factories\GrievanceLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['level', 'turnaround_time', 'contact_name', 'designation', 'address', 'phone', 'email', 'sort_order', 'status', 'level_hi', 'turnaround_time_hi', 'contact_name_hi', 'designation_hi', 'address_hi'])]
class GrievanceLevel extends Model
{
    /** @use HasFactory<GrievanceLevelFactory> */
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
            'level',
            'turnaround_time',
            'contact_name',
            'designation',
            'address',
        ];
    }
}
