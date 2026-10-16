<?php

use App\Modules\CreditScore\Enums\ScoreBand;
use App\Modules\CreditScore\Services\CreditScoreAdvice;

/**
 * Headline figures as a report document's insights() returns them, every one
 * healthy unless overridden.
 *
 * @param  array<string, int|null>  $overrides
 * @return array<string, int|null>
 */
function reportInsights(array $overrides = []): array
{
    return [
        'accounts_total' => 4,
        'accounts_active' => 3,
        'accounts_overdue' => 0,
        'overdue_amount' => 0,
        'accounts_written_off' => 0,
        'late_payments_last_12_months' => 0,
        'card_limit' => 200000,
        'card_balance' => 40000,
        'enquiries_last_6_months' => 1,
        'credit_age_months' => 70,
        'outstanding_balance' => 340000,
        ...$overrides,
    ];
}

/**
 * @return list<string>
 */
function tipTitles(CreditScoreAdvice $advice): array
{
    return array_column($advice->tips(), 'title');
}

it('places a score in its band and says how far the next band is', function (int $score, ScoreBand $band, ?string $gap) {
    $advice = new CreditScoreAdvice($score, null, 'Experian');

    expect($advice->band())->toBe($band)
        ->and($advice->nextBandGap())->toBe($gap);
})->with([
    'lowest score' => [300, ScoreBand::Poor, '250 points to Average'],
    'top of poor' => [549, ScoreBand::Poor, '1 point to Average'],
    'start of average' => [550, ScoreBand::Average, '100 points to Good'],
    'top of good' => [749, ScoreBand::Good, '1 point to Excellent'],
    'start of excellent' => [750, ScoreBand::Excellent, null],
    'highest score' => [900, ScoreBand::Excellent, null],
]);

it('places the score on the 300 to 900 dial', function (int $score, float $percent) {
    expect((new CreditScoreAdvice($score, null, 'CRIF'))->meterPercent())->toBe($percent);
})->with([
    [300, 0.0],
    [600, 50.0],
    [792, 82.0],
    [900, 100.0],
]);

it('rates each report factor against what lenders look for', function () {
    $advice = new CreditScoreAdvice(702, reportInsights([
        'late_payments_last_12_months' => 2,
        'card_balance' => 90000,
        'credit_age_months' => 18,
        'enquiries_last_6_months' => 5,
    ]), 'Equifax');

    expect(collect($advice->factors())->map(fn (array $factor) => [$factor['label'], $factor['value'], $factor['rating']])->all())->toBe([
        ['Payment history', '2', 'fair'],
        ['Card usage', '45%', 'fair'],
        ['Credit age', '1 yr 6 mths', 'poor'],
        ['Credit enquiries', '5', 'poor'],
    ]);
});

it('rates payment history poor while an amount is overdue, even with no late months', function () {
    $advice = new CreditScoreAdvice(702, reportInsights(['overdue_amount' => 3100]), 'Experian');

    expect($advice->factors()[0])->toMatchArray(['label' => 'Payment history', 'rating' => 'poor', 'status' => 'Needs attention']);
});

it('lists account totals and flags an overdue amount', function () {
    $advice = new CreditScoreAdvice(792, reportInsights(['overdue_amount' => 3100]), 'Experian');

    expect($advice->figures())->toBe([
        ['label' => 'Active accounts', 'value' => '3 of 4', 'alert' => false],
        ['label' => 'Outstanding', 'value' => '₹3,40,000', 'alert' => false],
        ['label' => 'Overdue', 'value' => '₹3,100', 'alert' => true],
    ]);
});

it('leads with the report\'s most urgent problems, then fills in with the band\'s own pointers', function () {
    $advice = new CreditScoreAdvice(612, reportInsights([
        'accounts_written_off' => 1,
        'overdue_amount' => 9108,
        'late_payments_last_12_months' => 1,
        'card_balance' => 130000,
        'enquiries_last_6_months' => 4,
    ]), 'CRIF');

    expect($advice->tipsHeading())->toBe('How to improve your score')
        ->and(tipTitles($advice))->toBe([
            'Resolve written-off or settled accounts',
            'Clear your overdue amount now',
            'Bring card usage under 30%',
            'Never miss a due date',
            'Pause new loan and card applications',
            'Keep your oldest accounts open',
        ])
        ->and($advice->tips()[1]['detail'])->toContain('₹9,108 is overdue')->toContain('reported to CRIF');
});

it('tells a visitor with a clean report what to keep doing', function () {
    $advice = new CreditScoreAdvice(812, reportInsights(), 'Experian');

    expect($advice->tipsHeading())->toBe('How to keep your score excellent')
        ->and(tipTitles($advice))->toBe([
            'Keep your clean repayment record',
            'Your card usage is healthy',
            'Keep your oldest accounts open',
            'Use your score to get better rates',
            'Review your report every few months',
            'Think twice before standing guarantor',
        ])
        ->and(collect($advice->tips())->pluck('tone')->unique()->values()->all())->toBe(['keep', 'info']);
});

it('gives only the band\'s pointers and no report factors when there is no report', function () {
    $advice = new CreditScoreAdvice(612, null, 'CIBIL');

    expect($advice->hasReport())->toBeFalse()
        ->and($advice->factors())->toBe([])
        ->and($advice->figures())->toBe([])
        ->and(tipTitles($advice))->toBe([
            'Make the next 6 months spotless',
            'Pay card bills in full, not the minimum',
            'Hold off on new loan applications',
            'Check your report for errors',
        ]);
});

it('explains how to build a first credit history when there is no score', function () {
    $advice = new CreditScoreAdvice(null, null, 'Experian');

    expect($advice->band())->toBeNull()
        ->and($advice->tipsHeading())->toBe('How to build your credit score')
        ->and(tipTitles($advice))->toBe([
            'Start with a secured credit card',
            'Repay a small loan on time',
            'Check again in a few months',
        ]);
});

it('words the band, factors and pointers in Hindi for a Hindi visitor', function () {
    app()->setLocale('hi');

    $advice = new CreditScoreAdvice(812, reportInsights(), 'Experian');

    expect($advice->band()->getLabel())->toBe('बेहतरीन')
        ->and($advice->tipsHeading())->toBe('अपना स्कोर बेहतरीन कैसे बनाए रखें')
        ->and($advice->factors()[2]['value'])->toBe('5 साल 10 महीने')
        ->and($advice->tips()[0]['title'])->toBe('अपना साफ़ भुगतान रिकॉर्ड बनाए रखें');
});
