<?php

namespace App\Modules\Customers\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\Journey\Models\JourneySession;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
