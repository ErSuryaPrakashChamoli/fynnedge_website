<?php

namespace App\Models;

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\Seoable;
use Database\Factories\LoanLandingPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['loan_product_id', 'group', 'title', 'slug', 'amount', 'excerpt', 'cta_label', 'body', 'sort_order', 'status', 'published_at', 'expires_at'])]
class LoanLandingPage extends Model
{
    /** @use HasFactory<LoanLandingPageFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable, Seoable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'group' => LandingPageGroup::class,
            'status' => PublishStatus::class,
            'amount' => 'decimal:2',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }
}
