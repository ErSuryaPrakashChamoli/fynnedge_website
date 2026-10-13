<?php

namespace App\Support\Rates;

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Enums\RateStatus;
use App\Enums\RateType;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Enums\RepaymentMethod;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The single source of every interest rate shown on the public site.
 *
 * Reads published, verified LenderProductRate rows once per request (from a
 * cache that is busted whenever a rate, offer or lender is saved) and applies
 * the date rules at READ time, against the current clock: effective_from /
 * effective_until in Indian calendar days, the promotion window to the
 * second. Because the cached data holds dates rather than decisions, an
 * expired offer drops off the site the moment it lapses — no cache TTL, cron
 * job or redeploy is involved.
 *
 * Registered as a scoped singleton (AppServiceProvider), so a queue worker or
 * Octane process never serves one request's memo to the next.
 */
final class RateBook
{
    public const TIMEZONE = LenderProductRate::TIMEZONE;

    private const CACHE_KEY = 'rate-book:published-rates:v1';

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $rows = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Drops the cached rows. Called from the saved/deleted hooks of every model
     * whose change can alter what the site shows (rates, offers, lenders).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);

        if (app()->resolved(self::class)) {
            self::instance()->rows = null;
        }
    }

    /**
     * Every live quote for one lender offer, best first: rates open to all
     * borrowers before segment-only ones, then lowest starting rate, then the
     * marketing team's featured flag and display priority.
     *
     * @return list<RateQuote>
     */
    public static function quotesFor(int $lenderProductId): array
    {
        return self::instance()->live()
            ->where('lenderProductId', $lenderProductId)
            ->sort(fn (RateQuote $a, RateQuote $b) => [$a->isForAllBorrowers() ? 0 : 1, $a->startingRate(), $b->featured, $b->displayPriority]
                <=> [$b->isForAllBorrowers() ? 0 : 1, $b->startingRate(), $a->featured, $a->displayPriority])
            ->values()
            ->all();
    }

    public static function quoteFor(int $lenderProductId): ?RateQuote
    {
        return self::quotesFor($lenderProductId)[0] ?? null;
    }

    /**
     * The product-wide "from X%" figure: the lowest live, verified rate open to
     * all borrowers among the product's active lender offers, in the unit the
     * product is quoted in. Null when no lender has one — callers then make no
     * rate claim at all rather than fall back to an unverified number.
     */
    public static function headlineFor(LoanProduct|int $loanProduct): ?RateHeadline
    {
        $loanProductId = $loanProduct instanceof LoanProduct ? $loanProduct->id : $loanProduct;

        $quotes = self::instance()->live()
            ->where('loanProductId', $loanProductId)
            ->filter(fn (RateQuote $quote) => $quote->isForAllBorrowers());

        $unit = self::instance()->unitFor($loanProductId);

        if ($unit !== null) {
            $quotes = $quotes->filter(fn (RateQuote $quote) => $quote->unit === $unit);
        }

        $lowest = $quotes->sortBy(fn (RateQuote $quote) => $quote->startingRate())->first();

        if ($lowest === null) {
            return null;
        }

        return new RateHeadline(
            lowest: $lowest,
            highest: (float) $quotes->max(fn (RateQuote $quote) => $quote->rateMax ?? $quote->rateMin),
            lenderCount: $quotes->pluck('lenderProductId')->unique()->count(),
        );
    }

    public static function headlineForSlug(string $loanProductSlug): ?RateHeadline
    {
        $loanProductId = collect(self::instance()->rows())->firstWhere('loan_product_slug', $loanProductSlug)['loan_product_id'] ?? null;

        return $loanProductId === null ? null : self::headlineFor($loanProductId);
    }

    /**
     * Live quotes across the whole catalogue, as of now.
     *
     * @return Collection<int, RateQuote>
     */
    public function live(): Collection
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
        $now = CarbonImmutable::now();

        return collect($this->rows())
            ->filter(fn (array $row) => $row['offer_active'] && $row['lender_active']
                && ($row['effective_from'] === null || $row['effective_from'] <= $today)
                && ($row['effective_until'] === null || $row['effective_until'] >= $today))
            ->map(fn (array $row) => $this->quote($row, $now))
            ->values();
    }

    private function unitFor(int $loanProductId): ?RateUnit
    {
        $category = collect($this->rows())->firstWhere('loan_product_id', $loanProductId)['loan_category'] ?? null;

        return $category ? LoanCategory::tryFrom($category)?->rateUnit() : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function quote(array $row, CarbonImmutable $now): RateQuote
    {
        $promoEndsAt = $row['promo_ends_at'] ? CarbonImmutable::parse($row['promo_ends_at']) : null;
        $promoStartsAt = $row['promo_starts_at'] ? CarbonImmutable::parse($row['promo_starts_at']) : null;
        $promoOpen = $row['promo_rate'] !== null && $promoEndsAt !== null && $promoEndsAt->gt($now)
            && ($promoStartsAt === null || $promoStartsAt->lte($now));

        return new RateQuote(
            rateId: $row['id'],
            lenderProductId: $row['lender_product_id'],
            loanProductId: $row['loan_product_id'],
            lenderName: $row['lender_name'],
            variant: $row['variant'],
            borrowerSegment: $row['borrower_segment'],
            rateMin: $row['rate_min'],
            rateMax: $row['rate_max'],
            unit: RateUnit::from($row['rate_unit']),
            rateType: $row['rate_type'] ? RateType::tryFrom($row['rate_type']) : null,
            repaymentMethod: $row['repayment_method'] ? RepaymentMethod::tryFrom($row['repayment_method']) : null,
            benchmark: $row['benchmark'],
            promoRate: $promoOpen ? $row['promo_rate'] : null,
            promoLabel: $promoOpen ? $row['promo_label'] : null,
            promoEndsAt: $promoOpen ? $promoEndsAt : null,
            eligibilityNotes: $row['eligibility_notes'],
            disclaimer: $row['disclaimer'],
            sourceUrl: $row['source_url'],
            lastVerifiedAt: CarbonImmutable::parse($row['last_verified_at'], self::TIMEZONE),
            featured: $row['is_featured'],
            displayPriority: $row['display_priority'],
        );
    }

    /**
     * Published, verified rate rows as plain arrays (cache-safe), with the
     * offer/lender state and product identity needed to filter them.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->rows ??= Cache::rememberForever(self::CACHE_KEY, fn (): array => LenderProductRate::query()
            ->toBase()
            ->join('lender_products', 'lender_products.id', '=', 'lender_product_rates.lender_product_id')
            ->join('lenders', 'lenders.id', '=', 'lender_products.lender_id')
            ->join('loan_products', 'loan_products.id', '=', 'lender_products.loan_product_id')
            ->where('lender_product_rates.status', RateStatus::Published->value)
            ->where('lender_product_rates.verification_status', RateVerificationStatus::Verified->value)
            ->whereNotNull('lender_product_rates.last_verified_at')
            ->whereNull('lenders.deleted_at')
            ->get([
                'lender_product_rates.*',
                'lender_products.loan_product_id',
                'lender_products.status as offer_status',
                'lenders.name as lender_name',
                'lenders.status as lender_status',
                'loan_products.slug as loan_product_slug',
                'loan_products.category as loan_category',
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'lender_product_id' => (int) $row->lender_product_id,
                'loan_product_id' => (int) $row->loan_product_id,
                'loan_product_slug' => $row->loan_product_slug,
                'loan_category' => $row->loan_category,
                'lender_name' => $row->lender_name,
                'offer_active' => $row->offer_status === LenderStatus::Active->value,
                'lender_active' => $row->lender_status === LenderStatus::Active->value,
                'variant' => $row->variant,
                'borrower_segment' => $row->borrower_segment,
                'rate_min' => (float) $row->rate_min,
                'rate_max' => $row->rate_max !== null ? (float) $row->rate_max : null,
                'rate_unit' => $row->rate_unit,
                'rate_type' => $row->rate_type,
                'repayment_method' => $row->repayment_method,
                'benchmark' => $row->benchmark,
                'promo_rate' => $row->promo_rate !== null ? (float) $row->promo_rate : null,
                'promo_label' => $row->promo_label,
                'promo_starts_at' => $row->promo_starts_at,
                'promo_ends_at' => $row->promo_ends_at,
                'effective_from' => $row->effective_from ? substr((string) $row->effective_from, 0, 10) : null,
                'effective_until' => $row->effective_until ? substr((string) $row->effective_until, 0, 10) : null,
                'eligibility_notes' => $row->eligibility_notes,
                'disclaimer' => $row->disclaimer,
                'source_url' => $row->source_url,
                'last_verified_at' => substr((string) $row->last_verified_at, 0, 10),
                'is_featured' => (bool) $row->is_featured,
                'display_priority' => (int) $row->display_priority,
            ])
            ->all());
    }
}
