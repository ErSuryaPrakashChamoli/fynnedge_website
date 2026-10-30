<?php

namespace App\Models;

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\InvalidatesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\Seoable;
use Database\Factories\LoanLandingPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['loan_product_id', 'group', 'title', 'slug', 'amount', 'city', 'excerpt', 'cta_label', 'body', 'sort_order', 'status', 'published_at', 'expires_at', 'title_hi', 'city_hi', 'excerpt_hi', 'cta_label_hi', 'body_hi'])]
class LoanLandingPage extends Model
{
    /** @use HasFactory<LoanLandingPageFactory> */
    use Auditable, HasFactory, HasPublicId, HasTranslations, InvalidatesContentCache, Publishable, Seoable, SoftDeletes;

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

    /**
     * The link text for lists already scoped to this page's loan product —
     * the header mega menu and the product overview. A location page reads
     * as just its city there ("Delhi" under Personal Loan); every other page
     * keeps its full title.
     */
    public function shortLabel(): string
    {
        return $this->group === LandingPageGroup::ByLocation && filled($this->city)
            ? $this->city
            : $this->title;
    }

    /**
     * @return list<string>
     */
    public function translatableAttributes(): array
    {
        return [
            'title',
            'city',
            'excerpt',
            'cta_label',
            'body',
        ];
    }
}
