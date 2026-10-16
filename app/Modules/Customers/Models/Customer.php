<?php

namespace App\Modules\Customers\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Journey\Models\JourneySession;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['full_name', 'email', 'phone'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasPublicId;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    public function journeySessions(): HasMany
    {
        return $this->hasMany(JourneySession::class);
    }

    public function crifReports(): HasMany
    {
        return $this->hasMany(CrifReport::class);
    }

    public function latestCrifReport(): HasOne
    {
        return $this->hasOne(CrifReport::class)->latestOfMany();
    }

    public function equifaxRequests(): HasMany
    {
        return $this->hasMany(EquifaxCreditRequest::class);
    }

    public function latestEquifaxRequest(): HasOne
    {
        return $this->hasOne(EquifaxCreditRequest::class)->latestOfMany();
    }

    public function experianRequests(): HasMany
    {
        return $this->hasMany(ExperianCreditRequest::class);
    }

    public function latestExperianRequest(): HasOne
    {
        return $this->hasOne(ExperianCreditRequest::class)->latestOfMany();
    }
}
