<?php

namespace App\Models;

use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Modules\Eligibility\Models\EmployerCategory;
use App\Modules\Eligibility\Models\EmployerRating;
use Database\Factories\LenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'slug', 'logo_path', 'description', 'status', 'type', 'serviceable_locations'])]
class Lender extends Model
{
    /** @use HasFactory<LenderFactory> */
    use Auditable, HasFactory, HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => LenderStatus::class,
            'type' => LenderType::class,
            'serviceable_locations' => 'array',
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function lenderProducts(): HasMany
    {
        return $this->hasMany(LenderProduct::class);
    }

    public function employerCategories(): HasMany
    {
        return $this->hasMany(EmployerCategory::class)->orderBy('order');
    }

    public function employerRatings(): HasMany
    {
        return $this->hasMany(EmployerRating::class);
    }
}
