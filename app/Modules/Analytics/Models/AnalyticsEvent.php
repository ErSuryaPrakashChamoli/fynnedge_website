<?php

namespace App\Modules\Analytics\Models;

use App\Models\LenderProduct;
use App\Models\LoanProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Journey\Models\JourneySession;
use Database\Factories\AnalyticsEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_key', 'journey_session_id', 'loan_product_id', 'lender_product_id', 'properties'])]
class AnalyticsEvent extends Model
{
    /** @use HasFactory<AnalyticsEventFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    protected static function newFactory(): AnalyticsEventFactory
    {
        return AnalyticsEventFactory::new();
    }

    protected function casts(): array
    {
        return [
            'event_key' => AnalyticsEventKey::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Only page views age out (config seo.page_views.retention_days); funnel
     * milestones are kept for good.
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('event_key', AnalyticsEventKey::PageView)
            ->where('created_at', '<', now()->subDays(max(30, (int) config('seo.page_views.retention_days', 400))));
    }

    public function journeySession(): BelongsTo
    {
        return $this->belongsTo(JourneySession::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }
}
