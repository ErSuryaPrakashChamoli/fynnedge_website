<?php

namespace App\Support\Rates;

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Enums\RateSourceType;
use App\Enums\RateStatus;
use App\Enums\RateType;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Enums\RepaymentMethod;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Localization\SiteLocale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single source of every interest rate shown on the public site.
 *
 * Reads published, verified LenderProductRate rows once per request (from a
 * cache invalidated whenever a rate, offer, lender or product is saved) and
 * applies the date rules at READ time, against the current clock: effective_from /
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

    /**
     * v4 added the Hindi (`{column}_hi`) values to each cached row, so a copy
     * cached before them is never read.
     */
    private const CACHE_KEY = 'rate-book:published-rates:v4';

    /**
     * Visitor-facing rate text with a translated sibling column (see
     * LenderProductRate::translatableAttributes()). Both languages are cached;
     * quote() picks one at read time, so the cache stays language-neutral.
     */
    private const TRANSLATED_COLUMNS = ['variant', 'borrower_segment', 'promo_label', 'eligibility_notes', 'disclaimer'];

    /**
     * A token that changes after every committed change to rates, offers,
     * lenders or products. The cached rows carry the token they were read
     * under and are only used while it is still current.
     */
    private const VERSION_KEY = 'rate-book:version';

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $rows = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Invalidates the cached rows. Called from the saved/deleted hooks of every
     * model whose change can alter what the site shows (rates, offers,
     * lenders, products).
     *
     * The new version token is written only AFTER the surrounding transaction
     * commits. Clearing inside the transaction let a page view that ran in that
     * moment cache the pre-publish rows again, and that stale copy then stayed
     * until the next change. Now such a copy is tagged with the old token and
     * is ignored by the next reader.
     */
    public static function flush(): void
    {
        self::forgetMemo();

        DB::afterCommit(function (): void {
            Cache::forever(self::VERSION_KEY, (string) Str::uuid());
            self::forgetMemo();
        });
    }

    /**
     * A RateBook that reads straight from the database, bypassing the cache —
     * for diagnostics (rates:status) to compare against what the site serves.
     */
    public static function fresh(): self
    {
        $book = new self;
        $book->rows = $book->loadRows();

        return $book;
    }

    private static function forgetMemo(): void
    {
        if (app()->resolved(self::class)) {
            self::instance()->rows = null;
        }
    }

    /**
     * Every live quote for one lender offer, best first: rates open to all
     * borrowers before segment-only ones, the lender's own rates before
     * third-party ones, then lowest starting rate, then the marketing team's
     * featured flag and display priority.
     *
     * @return list<RateQuote>
     */
    public static function quotesFor(int $lenderProductId): array
    {
        return self::instance()->live()
            ->where('lenderProductId', $lenderProductId)
            ->sort(fn (RateQuote $a, RateQuote $b) => [$a->isForAllBorrowers() ? 0 : 1, $a->isOfficial() ? 0 : 1, $a->startingRate(), $b->featured, $b->displayPriority]
                <=> [$b->isForAllBorrowers() ? 0 : 1, $b->isOfficial() ? 0 : 1, $b->startingRate(), $a->featured, $a->displayPriority])
            ->values()
            ->all();
    }

    public static function quoteFor(int $lenderProductId): ?RateQuote
    {
        return self::quotesFor($lenderProductId)[0] ?? null;
    }

    /**
     * The best quote read from the lender's own source — for anything that
     * ranks lenders or calculates with a rate, which a third-party figure must
     * not drive.
     */
    public static function officialQuoteFor(int $lenderProductId): ?RateQuote
    {
        return collect(self::quotesFor($lenderProductId))->first(fn (RateQuote $quote) => $quote->isOfficial());
    }

    /**
     * The product-wide "from X%" figure: the lowest live, verified rate open to
     * all borrowers among the product's active lender offers, in the unit the
     * product is quoted in. Null when no lender has one — callers then make no
     * rate claim at all rather than fall back to an unverified number.
     */
    public static function headlineFor(LoanProduct|int $loanProduct): ?RateHeadline
    {
        return self::instance()->headline($loanProduct instanceof LoanProduct ? $loanProduct->id : $loanProduct);
    }

    /**
     * headlineFor() against this book's rows — used directly on a fresh()
     * book to compute the headline from the database rather than the cache.
     */
    public function headline(int $loanProductId): ?RateHeadline
    {
        $quotes = $this->live()
            ->where('loanProductId', $loanProductId)
            // Only the lenders' own published rates may make a product-wide
            // claim; a third-party figure is shown only in its lender's row.
            ->filter(fn (RateQuote $quote) => $quote->isForAllBorrowers() && $quote->isOfficial());

        $unit = $this->unitFor($loanProductId);

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

    /**
     * The headline for a [rate:…] content token: the key is a loan product's URL
     * slug, or failing that a loan type (LoanCategory value), so
     * [rate:personal-loan] works whatever the personal loan product's own slug
     * is (production's is "Personal Loans").
     */
    public static function headlineForSlug(string $loanProductSlug): ?RateHeadline
    {
        $rows = collect(self::instance()->rows());
        $row = $rows->firstWhere('loan_product_slug', $loanProductSlug) ?? $rows->firstWhere('loan_category', $loanProductSlug);

        return $row === null ? null : self::headlineFor($row['loan_product_id']);
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
            variant: $this->localized($row, 'variant'),
            borrowerSegment: $this->localized($row, 'borrower_segment'),
            rateMin: $row['rate_min'],
            rateMax: $row['rate_max'],
            unit: RateUnit::from($row['rate_unit']),
            rateType: $row['rate_type'] ? RateType::tryFrom($row['rate_type']) : null,
            repaymentMethod: $row['repayment_method'] ? RepaymentMethod::tryFrom($row['repayment_method']) : null,
            benchmark: $row['benchmark'],
            promoRate: $promoOpen ? $row['promo_rate'] : null,
            promoLabel: $promoOpen ? $this->localized($row, 'promo_label') : null,
            promoEndsAt: $promoOpen ? $promoEndsAt : null,
            eligibilityNotes: $this->localized($row, 'eligibility_notes'),
            disclaimer: $this->localized($row, 'disclaimer'),
            sourceUrl: $row['source_url'],
            sourceType: RateSourceType::tryFrom((string) $row['source_type']) ?? RateSourceType::Official,
            sourceName: $row['source_name'],
            lastVerifiedAt: CarbonImmutable::parse($row['last_verified_at'], self::TIMEZONE),
            featured: $row['is_featured'],
            displayPriority: $row['display_priority'],
        );
    }

    /**
     * A row's text in the visitor's language: the `{column}_hi` value while the
     * site runs in Hindi and it is filled, otherwise the English. A blank English
     * value stays blank, so whether a rate is segment-only (and may drive the
     * product headline) never depends on the language.
     *
     * @param  array<string, mixed>  $row
     */
    private function localized(array $row, string $column): ?string
    {
        $value = $row[$column];

        if (blank($value) || ! SiteLocale::isTranslating()) {
            return $value;
        }

        $translated = $row[$column.'_'.SiteLocale::current()] ?? null;

        return blank($translated) ? $value : $translated;
    }

    /**
     * Published, verified rate rows as plain arrays (cache-safe), with the
     * offer/lender state and product identity needed to filter them.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $version = Cache::get(self::VERSION_KEY);
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ($cached['version'] ?? null) === $version && is_array($cached['rows'] ?? null)) {
            return $this->rows = $cached['rows'];
        }

        $rows = $this->loadRows();

        // Tagged with the version read BEFORE the query: if a change commits
        // while this request is reading, the token moves on and these rows are
        // refreshed by the next reader instead of being trusted.
        Cache::forever(self::CACHE_KEY, ['version' => $version, 'rows' => $rows]);

        return $this->rows = $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRows(): array
    {
        return LenderProductRate::query()
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
                'source_type' => $row->source_type,
                'source_name' => $row->source_name,
                'last_verified_at' => substr((string) $row->last_verified_at, 0, 10),
                'is_featured' => (bool) $row->is_featured,
                'display_priority' => (int) $row->display_priority,
                ...collect(self::TRANSLATED_COLUMNS)
                    ->mapWithKeys(fn (string $column): array => ["{$column}_hi" => $row->{"{$column}_hi"} ?? null])
                    ->all(),
            ])
            ->all();
    }
}
