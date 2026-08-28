<?php

namespace App\Modules\Eligibility\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\EmployerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'notes'])]
class Employer extends Model
{
    /** @use HasFactory<EmployerFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): EmployerFactory
    {
        return EmployerFactory::new();
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(EmployerRating::class);
    }
}
