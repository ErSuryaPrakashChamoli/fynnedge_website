<?php

use App\Support\Calculators\FlexiHybridTenure;

it('offers only an 8- or 9-year total tenure', function () {
    expect(FlexiHybridTenure::totalTenureOptions())->toBe([96, 108]);
});

it('splits the total tenure into the initial and subsequent tenure', function (int $total, int $initial, int $subsequent) {
    expect(FlexiHybridTenure::initialMonthsFor($total))->toBe($initial)
        ->and(FlexiHybridTenure::subsequentMonthsFor($total))->toBe($subsequent);
})->with([
    '8 years = 2 + 6' => [96, 24, 72],
    '9 years = 3 + 6' => [108, 36, 72],
]);

it('snaps any other tenure to the nearest allowed total tenure', function (int $total, int $expected) {
    expect(FlexiHybridTenure::nearestAllowed($total))->toBe($expected)
        ->and(FlexiHybridTenure::isAllowed($total))->toBeFalse();
})->with([
    [60, 96],
    [101, 96],
    [103, 108],
    [240, 108],
]);
