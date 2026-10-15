<?php

namespace App\Support\Rates;

use App\Enums\RateUnit;

/**
 * A loan product's "from X%" claim, derived from its lenders' live, verified
 * rates for ordinary borrowers. $lowest is the quote that backs the figure,
 * so wherever the headline appears, the lender and offer behind it are known.
 */
final readonly class RateHeadline
{
    /**
     * @param  int  $lenderCount  How many lenders have a live rate for all borrowers.
     */
    public function __construct(
        public RateQuote $lowest,
        public float $highest,
        public int $lenderCount,
    ) {}

    public function unit(): RateUnit
    {
        return $this->lowest->unit;
    }

    public function startingRate(): float
    {
        return $this->lowest->startingRate();
    }

    /**
     * "9.90%".
     */
    public function startingLabel(): string
    {
        return $this->lowest->startingLabel();
    }

    /**
     * "from 9.90% p.a." / "finance charges from 3.50% per month".
     */
    public function fromLabel(): string
    {
        $replace = ['rate' => $this->startingLabel(), 'unit' => $this->unit()->suffix()];

        return $this->unit() === RateUnit::PerMonth
            ? __('finance charges from :rate :unit', $replace)
            : __('from :rate :unit', $replace);
    }

    /**
     * "9.90%–30.50% p.a." across every lender with a live rate.
     */
    public function rangeLabel(): string
    {
        return RateFormat::range($this->startingRate(), $this->highest, $this->unit());
    }

    /**
     * Small print naming the offer behind the figure, e.g. "Lowest published
     * rate: Bajaj Finance (Festive offer until 31 Oct 2026). Final rate
     * depends on the lender's assessment."
     */
    public function backingNote(): string
    {
        $offer = $this->lowest->lenderName;

        if ($promotion = $this->lowest->promotionLabel()) {
            $offer .= " ({$promotion})";
        }

        return __("Lowest published rate: :offer. The rate you are offered depends on the lender's assessment of your profile.", ['offer' => $offer]);
    }
}
