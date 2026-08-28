<?php

namespace App\Modules\Eligibility\Models;

use App\Models\Lender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employer_id', 'lender_id', 'employer_category_id'])]
class EmployerRating extends Model
{
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployerCategory::class, 'employer_category_id');
    }
}
