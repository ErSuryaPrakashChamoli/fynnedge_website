<?php

namespace App\Models;

use App\Enums\RateStatus;
use App\Enums\RateType;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Enums\RepaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Support\Rates\RateBook;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\LenderProductRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

/**
 * One version of a lender's published interest rate for one lender offer.
 *
 * Versions of the same rate line share a series_id. Only a Draft can be
 * edited; submitted, published and archived versions are a record of what was
 * (or was about to be) shown to customers, so they are immutable apart from
 * their workflow columns — a change is a new Draft version, made through
 * App\Support\Rates\RateWorkflow, which also archives the version it replaces
 * when the new one is published.
 *
 * Public pages never read this model directly: they go through RateBook,
 * which applies the publication, effective-date and promotion rules.
 */
#[Fillable([
    'lender_product_id', 'series_id', 'version', 'variant', 'borrower_segment',
    'rate_min', 'rate_max', 'rate_unit', 'rate_type', 'repayment_method', 'benchmark',
    'promo_rate', 'promo_label', 'promo_starts_at', 'promo_ends_at',
    'effective_from', 'effective_until', 'eligibility_notes', 'disclaimer',
    'source_url', 'source_notes', 'last_verified_at', 'verification_status',
    'is_featured', 'display_priority', 'status', 'review_note',
    'submitted_by', 'submitted_at', 'published_by', 'published_at', 'archived_at',
])]
class LenderProductRate extends Model
{
    /** @use HasFactory<LenderProductRateFactory> */
    use Auditable, HasFactory, HasPublicId;

    /**
     * Rate dates (effective_from/until) are Indian calendar dates as printed on
     * the lender's schedule, so "today" for them is today in India, not UTC.
     */
    public const TIMEZONE = 'Asia/Kolkata';

    /**
     * A verified rate older than this is flagged for re-verification in the
     * admin, and cannot be submitted or published until it is re-checked.
     */
    public const REVERIFY_AFTER_DAYS = 90;

    /**
     * The window, in days, that the admin treats as "expiring soon".
     */
    public const EXPIRING_SOON_DAYS = 14;

    /**
     * Columns a non-draft version may still change: the workflow's own
     * bookkeeping, plus the two presentation switches a publisher may flip on a
     * live rate without the figures themselves changing.
     *
     * @var list<string>
     */
    private const MUTABLE_AFTER_SUBMISSION = [
        'status', 'review_note', 'submitted_by', 'submitted_at', 'published_by', 'published_at',
        'archived_at', 'is_featured', 'display_priority', 'updated_by', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_min' => 'decimal:2',
            'rate_max' => 'decimal:2',
            'promo_rate' => 'decimal:2',
            'rate_unit' => RateUnit::class,
            'rate_type' => RateType::class,
            'repayment_method' => RepaymentMethod::class,
            'verification_status' => RateVerificationStatus::class,
            'status' => RateStatus::class,
            'promo_starts_at' => 'datetime',
            'promo_ends_at' => 'datetime',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'last_verified_at' => 'date',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'is_featured' => 'boolean',
            'version' => 'integer',
            'display_priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $rate): void {
            $rate->series_id ??= (string) Str::uuid();
            $rate->version ??= 1;
            $rate->status ??= RateStatus::Draft;
            $rate->created_by ??= Auth::id();
        });

        static::saving(function (self $rate): void {
            $rate->updated_by = Auth::id() ?? $rate->updated_by;
        });

        static::updating(function (self $rate): void {
            $originalStatus = RateStatus::tryFrom((string) $rate->getRawOriginal('status'));

            if ($originalStatus === null || $originalStatus->isEditable()) {
                return;
            }

            $locked = array_diff(array_keys($rate->getDirty()), self::MUTABLE_AFTER_SUBMISSION);

            if ($locked !== []) {
                throw new LogicException(
                    "A {$originalStatus->getLabel()} rate cannot be edited (".implode(', ', $locked).'); create a new version instead.',
                );
            }
        });

        // Only an unsubmitted draft may be deleted: every other version is part
        // of the record of what customers were (or were about to be) shown.
        static::deleting(function (self $rate): void {
            if ($rate->status !== RateStatus::Draft) {
                throw new LogicException("A {$rate->status->getLabel()} rate is kept as history and cannot be deleted.");
            }
        });

        static::saved(fn () => RateBook::flush());
        static::deleted(fn () => RateBook::flush());
    }

    /**
     * Today's date in India, at midnight in the app timezone — the same shape
     * the 'date' casts produce, so date columns compare by calendar day and
     * not across a 5½-hour offset.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(self::TIMEZONE)->toDateString());
    }

    public function lenderProduct(): BelongsTo
    {
        return $this->belongsTo(LenderProduct::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Every version of this rate line, this one included, newest first.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'series_id', 'series_id')->orderByDesc('version');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', RateStatus::Published);
    }

    /**
     * Published and inside its effective window today — the rates that may be
     * shown. Promotion dates are applied on top of this by effectiveRate().
     */
    public function scopeLive(Builder $query): void
    {
        $today = self::today()->toDateString();

        $query->published()
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today));
    }

    public function scopeExpired(Builder $query): void
    {
        $query->published()->whereDate('effective_until', '<', self::today()->toDateString());
    }

    /**
     * Live rates whose validity or promotion ends within the given window.
     */
    public function scopeExpiringSoon(Builder $query, int $days = self::EXPIRING_SOON_DAYS): void
    {
        $today = self::today();
        $horizon = $today->addDays($days);

        $query->live()->where(fn (Builder $query) => $query
            ->whereBetween('effective_until', [$today->toDateString(), $horizon->toDateString()])
            ->orWhereBetween('promo_ends_at', [now(), $horizon->endOfDay()]));
    }

    /**
     * Unverified, or verified so long ago that it must be re-checked. Archived
     * versions are history, not work, so they never count.
     */
    public function scopeNeedsVerification(Builder $query): void
    {
        $query->where('status', '!=', RateStatus::Archived)
            ->where(fn (Builder $query) => $query
                ->where('verification_status', RateVerificationStatus::Unverified)
                ->orWhereNull('last_verified_at')
                ->orWhereDate('last_verified_at', '<', self::today()->subDays(self::REVERIFY_AFTER_DAYS)->toDateString()));
    }

    public function isLive(): bool
    {
        $today = self::today();

        return $this->status === RateStatus::Published
            && ($this->effective_from === null || $this->effective_from->lte($today))
            && ($this->effective_until === null || $this->effective_until->gte($today));
    }

    public function isPromoActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->promo_rate !== null
            && $this->promo_ends_at !== null
            && $this->promo_ends_at->gt($at)
            && ($this->promo_starts_at === null || $this->promo_starts_at->lte($at));
    }

    /**
     * The starting rate a customer can be quoted right now: the promotional
     * rate while its window is open, otherwise the published product rate.
     */
    public function effectiveRate(?CarbonInterface $at = null): float
    {
        return $this->isPromoActive($at) ? (float) $this->promo_rate : (float) $this->rate_min;
    }

    public function needsReverification(): bool
    {
        return $this->verification_status !== RateVerificationStatus::Verified
            || $this->last_verified_at === null
            || $this->last_verified_at->lt(self::today()->subDays(self::REVERIFY_AFTER_DAYS));
    }

    /**
     * The admin-facing state: the stored workflow status, except that a
     * published version past its effective window reads "Expired" and one not
     * yet in it reads "Scheduled".
     */
    public function displayStatus(): string
    {
        if ($this->status !== RateStatus::Published) {
            return $this->status->getLabel();
        }

        $today = self::today();

        return match (true) {
            $this->effective_until !== null && $this->effective_until->lt($today) => 'Expired',
            $this->effective_from !== null && $this->effective_from->gt($today) => 'Scheduled',
            default => 'Published',
        };
    }

    public function displayStatusColor(): string
    {
        return match ($this->displayStatus()) {
            'Expired' => 'danger',
            'Scheduled' => 'info',
            default => $this->status->getColor(),
        };
    }

    /**
     * "HDFC Bank — Personal Loan", for selects, notifications and exports.
     */
    public function offerLabel(): string
    {
        $offer = $this->lenderProduct;

        return $offer ? "{$offer->lender?->name} — {$offer->loanProduct?->name}" : 'Unknown offer';
    }
}
