<?php

namespace App\Support\Rates;

use App\Enums\RateType;
use App\Enums\RateUnit;
use App\Enums\RepaymentMethod;
use Carbon\CarbonImmutable;

/**
 * One lender's live, verified rate as it may be shown right now. Built by
 * RateBook with the publication, effective-date and promotion rules already
 * applied: $promoRate is only set while the promotion is open, so a view can
 * never print an expired offer by accident.
 */
final readonly class RateQuote
{
    public function __construct(
        public int $rateId,
        public int $lenderProductId,
        public int $loanProductId,
        public string $lenderName,
        public ?string $variant,
        public ?string $borrowerSegment,
        public float $rateMin,
        public ?float $rateMax,
        public RateUnit $unit,
        public ?RateType $rateType,
        public ?RepaymentMethod $repaymentMethod,
        public ?string $benchmark,
        public ?float $promoRate,
        public ?string $promoLabel,
        public ?CarbonImmutable $promoEndsAt,
        public ?string $eligibilityNotes,
        public ?string $disclaimer,
        public ?string $sourceUrl,
        public CarbonImmutable $lastVerifiedAt,
        public bool $featured,
        public int $displayPriority,
    ) {}

    /**
     * The lowest rate a customer can be quoted today: the open promotional
     * rate, otherwise the lender's published starting rate.
     */
    public function startingRate(): float
    {
        return $this->promoRate ?? $this->rateMin;
    }

    public function isPromotional(): bool
    {
        return $this->promoRate !== null;
    }

    /**
     * A rate that only some borrowers get (women, government employees, a
     * pre-approved base…) is shown in that lender's row with its condition,
     * but never drives a product-wide "from X%" headline.
     */
    public function isForAllBorrowers(): bool
    {
        return blank($this->borrowerSegment);
    }

    /**
     * "9.90%" — the starting figure on its own.
     */
    public function startingLabel(): string
    {
        return RateFormat::percent($this->startingRate());
    }

    /**
     * "9.90%–22.00% p.a." — the lender's published product range, never the
     * promotional rate, which is shown separately with its end date.
     */
    public function rangeLabel(): string
    {
        return RateFormat::range($this->rateMin, $this->rateMax, $this->unit);
    }

    /**
     * "Festive offer: 9.49% p.a. until 31 Oct 2026".
     */
    public function promotionLabel(): ?string
    {
        if (! $this->isPromotional()) {
            return null;
        }

        $label = filled($this->promoLabel) ? "{$this->promoLabel}: " : 'Offer: ';

        return $label.RateFormat::percent($this->promoRate)." {$this->unit->suffix()} until "
            .$this->promoEndsAt->timezone(RateBook::TIMEZONE)->format('j M Y');
    }

    /**
     * "Rate verified 13 Oct 2026" — when FynnEdge last checked the figure
     * against the lender's own published schedule.
     */
    public function verifiedLabel(): string
    {
        return 'Rate verified '.$this->lastVerifiedAt->format('j M Y');
    }

    /**
     * The repayment structure, named only when it is not an ordinary
     * reducing-balance EMI (a flat 10% costs far more than a reducing 10%).
     */
    public function repaymentNote(): ?string
    {
        return $this->repaymentMethod !== null && $this->repaymentMethod !== RepaymentMethod::ReducingBalance
            ? $this->repaymentMethod->getLabel()
            : null;
    }
}
