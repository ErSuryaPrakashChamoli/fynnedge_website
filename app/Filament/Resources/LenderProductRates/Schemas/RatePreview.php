<?php

namespace App\Filament\Resources\LenderProductRates\Schemas;

use App\Enums\RateSourceType;
use App\Enums\RateUnit;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Support\Rates\RateFormat;
use App\Support\Rates\RatePublicationRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * Renders, inside the admin form, the exact wording the public site will use
 * for the rate being edited, plus everything still blocking its publication —
 * so an editor sees the customer's view and the checklist before submitting.
 */
final class RatePreview
{
    /**
     * @param  array<string, mixed>  $state
     */
    public static function render(array $state): HtmlString
    {
        $offer = filled($state['lender_product_id'] ?? null)
            ? LenderProduct::query()->with(['lender', 'loanProduct'])->find($state['lender_product_id'])
            : null;

        if ($offer === null || ! is_numeric($state['rate_min'] ?? null)) {
            return new HtmlString('<p>Choose the loan product, lender and starting rate to see how this rate will appear.</p>');
        }

        $state['source_type'] = $state['source_type'] instanceof RateSourceType
            ? $state['source_type']
            : (RateSourceType::tryFrom((string) ($state['source_type'] ?? '')) ?? RateSourceType::Official);
        $isThirdParty = $state['source_type'] === RateSourceType::ThirdParty;

        $rate = new LenderProductRate(Arr::only($state, (new LenderProductRate)->getFillable()));
        $rate->rate_unit ??= RateUnit::PerAnnum;
        $rate->setRelation('lenderProduct', $offer);

        $unit = $rate->rate_unit;
        $rateMax = is_numeric($state['rate_max'] ?? null) ? (float) $state['rate_max'] : null;

        $lines = [
            'Lender comparison table: <strong>'.e($offer->lender?->name).'</strong> — '.e(RateFormat::range((float) $state['rate_min'], $rateMax, $unit)),
        ];

        if (is_numeric($state['promo_rate'] ?? null)) {
            $lines[] = 'Offer badge: '.e(($state['promo_label'] ?: 'Offer').': '.RateFormat::percent((float) $state['promo_rate'])." {$unit->suffix()}")
                .' — shown only between the promotion dates, and never after they end.';
        }

        $lines[] = match (true) {
            filled($state['borrower_segment'] ?? null) => 'Only for: <strong>'.e($state['borrower_segment']).'</strong> — shown with this condition in the lender\'s row; never used for the product headline.',
            $isThirdParty => 'Third-party figure: shown only in the lender\'s row; never used for the "'.e($offer->loanProduct?->name).' from X%" headline, the "Lowest rate" badge or calculators.',
            default => 'Open to all borrowers: can set the "'.e($offer->loanProduct?->name).' from X%" headline if it is the lowest live rate.',
        };

        if (filled($state['last_verified_at'] ?? null)) {
            $date = e(CarbonImmutable::parse($state['last_verified_at'])->format('j M Y'));
            $lines[] = $isThirdParty
                ? 'Small print: As listed on '.e($state['source_name'] ?: '…').", {$date} · confirm with ".e($offer->lender?->name).' — linked to that page.'
                : "Small print: Rate verified {$date}, with a link to the lender's page.";
        }

        $problems = RatePublicationRules::problems($rate);

        $checklist = $problems === []
            ? '<p><strong>Ready to submit for approval.</strong> Nothing changes on the site until a publisher publishes it.</p>'
            : '<p><strong>Before this can be submitted or published:</strong></p><ul class="list-disc ps-5">'
                .collect($problems)->map(fn (string $problem) => '<li>'.e($problem).'</li>')->implode('').'</ul>';

        return new HtmlString(
            '<ul class="list-disc ps-5 space-y-1">'.collect($lines)->map(fn (string $line) => "<li>{$line}</li>")->implode('').'</ul>'
            .'<div class="mt-3">'.$checklist.'</div>',
        );
    }
}
