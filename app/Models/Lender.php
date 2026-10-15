<?php

namespace App\Models;

use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use App\Modules\Eligibility\Models\EmployerCategory;
use App\Modules\Eligibility\Models\EmployerRating;
use App\Support\Rates\RateBook;
use Database\Factories\LenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'slug', 'logo_path', 'description', 'status', 'type', 'serviceable_locations', 'description_hi'])]
class Lender extends Model
{
    /** @use HasFactory<LenderFactory> */
    use Auditable, HasFactory, HasPublicId, HasTranslations, SoftDeletes;

    /**
     * A lender's status (and soft delete) decides whether its rates may show,
     * so any change drops RateBook's cached rows.
     */
    protected static function booted(): void
    {
        static::saved(fn () => RateBook::flush());
        static::deleted(fn () => RateBook::flush());
        static::restored(fn () => RateBook::flush());
    }

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

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'description',
        ];
    }
}
