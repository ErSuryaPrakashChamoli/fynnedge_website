<?php

use App\Support\Calculators\FlexiHybridTenure;

it('derives each lender\'s initial + subsequent structure from its min/max/initial tenure', function (int $min, int $max, int $initial, array $expected) {
    expect(FlexiHybridTenure::options($min, $max, $initial))->toBe($expected);
})->with([
    'Kotak 1 + 5' => [72, 72, 12, [['total' => 72, 'initial' => 12, 'subsequent' => 60]]],
    'Piramal / Tata 2 + 5' => [84, 84, 24, [['total' => 84, 'initial' => 24, 'subsequent' => 60]]],
    'Bajaj 2 + 6 or 3 + 6' => [96, 108, 24, [
        ['total' => 96, 'initial' => 24, 'subsequent' => 72],
        ['total' => 108, 'initial' => 36, 'subsequent' => 72],
    ]],
]);

it('has no structure when the tenure fields cannot describe one', function (?int $min, ?int $max, ?int $initial) {
    expect(FlexiHybridTenure::options($min, $max, $initial))->toBe([]);
})->with([
    'no initial tenure' => [96, 108, null],
    'no minimum tenure' => [null, 108, 24],
    'initial not shorter than the minimum' => [12, 60, 24],
]);

it('picks the exact or closest structure for a total tenure', function () {
    $options = FlexiHybridTenure::options(96, 108, 24);

    expect(FlexiHybridTenure::nearest($options, 108)['initial'])->toBe(36)
        ->and(FlexiHybridTenure::nearest($options, 60)['total'])->toBe(96)
        ->and(FlexiHybridTenure::nearest([], 96))->toBeNull();
});

it('labels a structure in years', function () {
    expect(FlexiHybridTenure::label(['total' => 72, 'initial' => 12, 'subsequent' => 60]))->toBe('1 + 5 yrs')
        ->and(FlexiHybridTenure::label(['total' => 66, 'initial' => 6, 'subsequent' => 60]))->toBe('0.5 + 5 yrs');
});
