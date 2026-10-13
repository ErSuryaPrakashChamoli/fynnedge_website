<?php

namespace App\Support\Rates;

use App\Enums\RateStatus;
use App\Enums\RateType;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Enums\RepaymentMethod;
use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Loads the October 2026 rate audit (database/seeders/data/rate-audit-2026-10-13.json)
 * into lender_product_rates — and publishes nothing. For every lender offer:
 *
 *  - the lender's primary product rate, verified on its own website, becomes a
 *    rate AWAITING APPROVAL with its evidence attached, for a publisher to
 *    review and publish in Catalog → Interest Rates — unless the source is a
 *    policy band or a disclosure of rates already charged (NOT_AN_OFFER),
 *    which stays a verified draft with that caveat;
 *  - other verified variants (EV loans, women borrowers, disclosed ranges…)
 *    become verified DRAFTS the team may submit if they want them shown;
 *  - a rate seen only in a search snippet becomes an unverified draft with the
 *    official link to check;
 *  - where nothing official was found, the offer's old (unsourced) figure is
 *    kept as an unverified draft so it is not lost, with what the audit found.
 *
 * An offer that already has any rate rows is skipped, so running it twice
 * changes nothing. Lenders whose product the audit found NOT offered get no
 * rate at all and are listed in the result for the team to deactivate.
 */
final class RateAuditImport
{
    public const DATASET = 'database/seeders/data/rate-audit-2026-10-13.json';

    /**
     * Official, but not an advertised starting rate: an interest-rate-policy
     * band covering a whole segment, or a quarterly disclosure of the rates
     * actually charged. Kept as drafts so approving the queue in bulk can
     * never turn one into a product's "from X%" headline.
     */
    private const NOT_AN_OFFER = '/disclos|disbursed|\bactual\b|\bQ[1-4]\s?(?:FY|20)|interest rate policy|segment band|IRP band|indicative range|rate range|pricing range|mid-corp/i';

    /**
     * @return array{awaiting_approval: int, verified_drafts: int, unverified_drafts: int, legacy_drafts: int, offers_without_rate: int, not_offered: list<string>}
     */
    public static function run(?string $path = null): array
    {
        $dataset = json_decode((string) file_get_contents($path ?? base_path(self::DATASET)), true, flags: JSON_THROW_ON_ERROR);
        $researchedOn = CarbonImmutable::parse($dataset['researched_on']);

        $research = collect($dataset['records'])->groupBy(fn (array $record) => Str::slug($record['lender']).'|'.$record['product_slug']);

        $result = ['awaiting_approval' => 0, 'verified_drafts' => 0, 'unverified_drafts' => 0, 'legacy_drafts' => 0, 'offers_without_rate' => 0, 'not_offered' => []];

        LenderProduct::query()
            ->with(['lender' => fn ($query) => $query->withTrashed(), 'loanProduct'])
            ->whereDoesntHave('rates')
            ->get()
            ->each(function (LenderProduct $offer) use ($research, $researchedOn, &$result): void {
                if ($offer->lender === null || $offer->loanProduct === null) {
                    return;
                }

                /** @var Collection<int, array<string, mixed>> $records */
                $records = $research->get(self::key($offer->lender, $offer->loanProduct->slug), collect());

                if ($records->contains('status', 'NOT_OFFERED') && ! $records->contains(fn (array $record) => $record['status'] === 'VERIFIED' && $record['rate_min'] !== null)) {
                    $result['not_offered'][] = "{$offer->lender->name} — {$offer->loanProduct->name}";
                    $result['offers_without_rate']++;

                    return;
                }

                $created = 0;

                foreach ($records as $record) {
                    $kind = self::import($offer, $record, $researchedOn);

                    if ($kind !== null) {
                        $result[$kind]++;
                        $created++;
                    }
                }

                if ($created === 0 && $offer->interest_rate_from !== null) {
                    self::importLegacy($offer, $records);
                    $result['legacy_drafts']++;
                    $created++;
                }

                if ($created === 0) {
                    $result['offers_without_rate']++;
                }
            });

        return $result;
    }

    private static function key(Lender $lender, string $productSlug): string
    {
        return Str::slug($lender->name).'|'.$productSlug;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return 'awaiting_approval'|'verified_drafts'|'unverified_drafts'|null
     */
    private static function import(LenderProduct $offer, array $record, CarbonImmutable $researchedOn): ?string
    {
        if (! in_array($record['status'], ['VERIFIED', 'SNIPPET_ONLY'], true) || $record['rate_min'] === null) {
            return null;
        }

        $expectedUnit = $offer->loanProduct->category->rateUnit();
        $unit = RateUnit::tryFrom((string) $record['rate_unit']) ?? $expectedUnit;
        $withinLimits = $record['rate_min'] > 0 && $record['rate_min'] <= $unit->maximum()
            && ($record['rate_max'] === null || ($record['rate_max'] >= $record['rate_min'] && $record['rate_max'] <= $unit->maximum()));

        $verified = $record['status'] === 'VERIFIED' && $unit === $expectedUnit && $withinLimits
            && RatePublicationRules::isOfficialSourceUrl($record['source_url']);

        $isPolicyRange = preg_match(self::NOT_AN_OFFER, "{$record['variant']} {$record['source_title']}") === 1;
        $primary = $verified && $record['primary'] && ! $isPolicyRange;

        $caveats = array_values(array_filter([
            $isPolicyRange ? 'The source is a policy band or a disclosure of rates actually charged, not an advertised starting rate — submit it only if you are comfortable presenting it as this lender\'s current rate.' : null,
            $record['status'] === 'SNIPPET_ONLY' ? 'Only a search-result snippet from the lender\'s site was seen — open the link and confirm before marking verified.' : null,
            $unit !== $expectedUnit ? "The lender quotes this {$unit->suffix()}; the site shows {$offer->loanProduct->name} rates {$expectedUnit->suffix()} — decide how to present it before verifying." : null,
            ! $withinLimits ? 'The figures fall outside the accepted range for this unit — re-check them.' : null,
        ]));

        $offerExpiry = self::date($record['offer_expiry']);

        LenderProductRate::query()->create([
            'lender_product_id' => $offer->id,
            'variant' => Str::limit((string) $record['variant'], 250, '…') ?: null,
            // A primary record is the lender's general product rate, so it is
            // open to all borrowers; any other variant is shown with its
            // condition and never drives a product headline.
            'borrower_segment' => $record['primary'] ? null : (Str::limit((string) ($record['borrower_segment'] ?: $record['variant'] ?: 'Specific borrowers'), 250, '…')),
            'rate_min' => $record['rate_min'],
            'rate_max' => $record['rate_max'],
            'rate_unit' => $unit,
            'rate_type' => RateType::tryFrom((string) $record['rate_type']),
            'repayment_method' => RepaymentMethod::tryFrom((string) $record['repayment_method']),
            'benchmark' => Str::limit((string) $record['benchmark'], 250, '…') ?: null,
            'effective_until' => $offerExpiry !== null && $offerExpiry->gte(LenderProductRate::today()) ? $offerExpiry->toDateString() : null,
            'source_url' => RatePublicationRules::isOfficialSourceUrl($record['source_url']) ? $record['source_url'] : null,
            'source_notes' => self::sourceNotes($offer, $record, $caveats),
            'last_verified_at' => $verified ? $researchedOn->toDateString() : null,
            'verification_status' => $verified ? RateVerificationStatus::Verified : RateVerificationStatus::Unverified,
            'display_priority' => $record['primary'] ? 10 : 0,
            'status' => $primary ? RateStatus::PendingApproval : RateStatus::Draft,
            'submitted_at' => $primary ? now() : null,
        ]);

        return match (true) {
            $primary => 'awaiting_approval',
            $verified => 'verified_drafts',
            default => 'unverified_drafts',
        };
    }

    /**
     * Keeps an offer's pre-audit figure as an unverified draft, with whatever
     * the audit found, so the team can see what was shown and why it was not
     * confirmed.
     *
     * @param  Collection<int, array<string, mixed>>  $records
     */
    private static function importLegacy(LenderProduct $offer, Collection $records): void
    {
        $found = $records->map(fn (array $record): string => trim("{$record['status']}: ".collect([
            $record['rate_max'] !== null && $record['rate_min'] === null ? "lender publishes only a maximum of {$record['rate_max']}%" : null,
            $record['notes'],
            $record['source_url'],
        ])->filter()->implode(' — ')))->implode("\n");

        LenderProductRate::query()->create([
            'lender_product_id' => $offer->id,
            'rate_min' => $offer->interest_rate_from,
            'rate_max' => $offer->interest_rate_to,
            'rate_unit' => RateUnit::PerAnnum,
            'source_notes' => "Pre-audit figure from the lender offer, with no source on record. Not shown on the website until it is verified and published.\n\n"
                .($found !== '' ? "What the 13 Oct 2026 audit found on the lender's own website:\n{$found}" : 'Not covered by the 13 Oct 2026 audit.'),
            'verification_status' => RateVerificationStatus::Unverified,
            'status' => RateStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $caveats
     */
    private static function sourceNotes(LenderProduct $offer, array $record, array $caveats): string
    {
        $before = $offer->interest_rate_from !== null
            ? 'Before the audit the website showed '.$offer->interest_rate_from.'%'.($offer->interest_rate_to !== null ? '–'.$offer->interest_rate_to.'%' : '').'.'
            : null;

        return collect([
            ...$caveats,
            $record['source_title'],
            $record['evidence'] ? "\"{$record['evidence']}\"" : null,
            $record['page_effective_date'] ? "Rates on the page effective {$record['page_effective_date']}." : null,
            $record['borrower_segment'] ? "Applies to: {$record['borrower_segment']}" : null,
            $record['conditions'] ? "Conditions: {$record['conditions']}" : null,
            $record['processing_fee'] ? "Processing fee per the source: {$record['processing_fee']}" : null,
            $record['notes'] ? "Researcher's notes: {$record['notes']}" : null,
            $before,
            'Imported from the 13 Oct 2026 rate audit.',
        ])->filter()->implode("\n");
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return CarbonImmutable::parse($value, LenderProductRate::TIMEZONE);
    }
}
