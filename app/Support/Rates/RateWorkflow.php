<?php

namespace App\Support\Rates;

use App\Enums\RateStatus;
use App\Enums\RateUnit;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Every state change a rate can make, in one place:
 *
 *   Draft ──submit──▶ Awaiting approval ──publish──▶ Published ──withdraw──▶ Archived
 *     ▲                    │                            │
 *     └──────reject────────┘                            └─(a newer version is published)─▶ Archived
 *
 * Publishing a version archives whichever version of the same series was live,
 * so history is never overwritten. "New version" copies any submitted,
 * published or archived version into a fresh Draft — that is also how an old
 * version is restored: it goes back through approval rather than straight onto
 * the site.
 *
 * Each transition re-checks the actor's permission (Submit/Publish/Create:
 * LenderProductRate) as well as the state, because the Filament buttons hiding
 * themselves is presentation, not authorisation.
 */
final class RateWorkflow
{
    public static function submit(LenderProductRate $rate, User $actor): LenderProductRate
    {
        self::authorize($actor, 'submit', $rate);

        return DB::transaction(function () use ($rate, $actor): LenderProductRate {
            $rate = self::locked($rate);
            self::expectStatus($rate, [RateStatus::Draft], 'Only a draft can be submitted for approval.');
            self::expectPublishable($rate);

            $rate->update([
                'status' => RateStatus::PendingApproval,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'review_note' => null,
            ]);

            return $rate;
        });
    }

    /**
     * Approves and publishes a draft or a submitted version. A publisher may
     * publish their own draft directly; the record keeps who submitted and who
     * published either way.
     */
    public static function publish(LenderProductRate $rate, User $actor): LenderProductRate
    {
        self::authorize($actor, 'publish', $rate);

        return DB::transaction(function () use ($rate, $actor): LenderProductRate {
            $rate = self::locked($rate);
            self::expectStatus($rate, [RateStatus::Draft, RateStatus::PendingApproval], 'Only a draft or a submitted rate can be published.');
            self::expectPublishable($rate);

            LenderProductRate::query()
                ->where('series_id', $rate->series_id)
                ->where('status', RateStatus::Published)
                ->whereKeyNot($rate->getKey())
                ->get()
                ->each(fn (LenderProductRate $previous) => $previous->update([
                    'status' => RateStatus::Archived,
                    'archived_at' => now(),
                ]));

            $rate->update([
                'status' => RateStatus::Published,
                'published_by' => $actor->id,
                'published_at' => now(),
                'submitted_by' => $rate->submitted_by ?? $actor->id,
                'submitted_at' => $rate->submitted_at ?? now(),
                'review_note' => null,
            ]);

            self::syncOfferRate($rate->lenderProduct);

            return $rate;
        });
    }

    /**
     * Sends a submitted version back to its editor as a draft, with the
     * reviewer's reason.
     */
    public static function reject(LenderProductRate $rate, User $actor, string $reason): LenderProductRate
    {
        self::authorize($actor, 'publish', $rate);

        return DB::transaction(function () use ($rate, $reason): LenderProductRate {
            $rate = self::locked($rate);
            self::expectStatus($rate, [RateStatus::PendingApproval], 'Only a submitted rate can be sent back.');

            $rate->update([
                'status' => RateStatus::Draft,
                'review_note' => $reason,
            ]);

            return $rate;
        });
    }

    /**
     * Takes a live rate off the site without a replacement — the lender's row
     * then reads "Check current rates" until a new version is published.
     */
    public static function withdraw(LenderProductRate $rate, User $actor): LenderProductRate
    {
        self::authorize($actor, 'publish', $rate);

        return DB::transaction(function () use ($rate): LenderProductRate {
            $rate = self::locked($rate);
            self::expectStatus($rate, [RateStatus::Published], 'Only a published rate can be withdrawn.');

            $rate->update([
                'status' => RateStatus::Archived,
                'archived_at' => now(),
            ]);

            self::syncOfferRate($rate->lenderProduct);

            return $rate;
        });
    }

    /**
     * Copies a version into a new Draft in the same series, for editing a live
     * rate or restoring an earlier one. Refuses while the series already has a
     * draft or submitted version, so two people never edit competing drafts.
     */
    public static function draftFrom(LenderProductRate $rate, User $actor): LenderProductRate
    {
        self::authorize($actor, 'create', LenderProductRate::class);

        return DB::transaction(function () use ($rate): LenderProductRate {
            $versions = LenderProductRate::query()
                ->where('series_id', $rate->series_id)
                ->lockForUpdate()
                ->get();

            $open = $versions->first(fn (LenderProductRate $version) => in_array($version->status, [RateStatus::Draft, RateStatus::PendingApproval], true));

            if ($open !== null) {
                throw new RateWorkflowException(["Version {$open->version} of this rate is already {$open->status->getLabel()} — finish or delete it first."]);
            }

            $draft = $rate->replicate([
                'public_id', 'version', 'status', 'review_note', 'created_by', 'updated_by',
                'submitted_by', 'submitted_at', 'published_by', 'published_at', 'archived_at',
            ]);
            $draft->version = (int) $versions->max('version') + 1;
            $draft->status = RateStatus::Draft;
            $draft->save();

            return $draft;
        });
    }

    /**
     * Keeps lender_products.interest_rate_from/to equal to the offer's live
     * per-annum range. Those two columns are no longer shown to visitors (every
     * public rate comes from RateBook); they remain the input the eligibility
     * engine's FOIR estimate uses, so they follow what is published. With no
     * live per-annum rate the columns are left as they were rather than
     * blanked, because blanking would silently change eligibility outcomes.
     */
    public static function syncOfferRate(?LenderProduct $offer): void
    {
        if ($offer === null) {
            return;
        }

        $live = $offer->rates()
            ->live()
            ->where('rate_unit', RateUnit::PerAnnum)
            ->get();

        if ($live->isEmpty()) {
            return;
        }

        $forAll = $live->filter(fn (LenderProductRate $rate) => blank($rate->borrower_segment));
        $basis = $forAll->isNotEmpty() ? $forAll : $live;

        $offer->update([
            'interest_rate_from' => $basis->min(fn (LenderProductRate $rate) => (float) $rate->rate_min),
            'interest_rate_to' => $basis->max(fn (LenderProductRate $rate) => (float) ($rate->rate_max ?? $rate->rate_min)),
        ]);
    }

    private static function locked(LenderProductRate $rate): LenderProductRate
    {
        return LenderProductRate::query()->with('lenderProduct.loanProduct')->lockForUpdate()->findOrFail($rate->getKey());
    }

    /**
     * @param  list<RateStatus>  $allowed
     */
    private static function expectStatus(LenderProductRate $rate, array $allowed, string $message): void
    {
        if (! in_array($rate->status, $allowed, true)) {
            throw new RateWorkflowException([$message." This rate is {$rate->status->getLabel()}."]);
        }
    }

    private static function expectPublishable(LenderProductRate $rate): void
    {
        if ($problems = RatePublicationRules::problems($rate)) {
            throw new RateWorkflowException($problems);
        }
    }

    /**
     * @param  LenderProductRate|class-string<LenderProductRate>  $subject
     */
    private static function authorize(User $actor, string $ability, LenderProductRate|string $subject): void
    {
        if (! $actor->can($ability, $subject)) {
            throw new AuthorizationException("You do not have permission to {$ability} interest rates.");
        }
    }
}
