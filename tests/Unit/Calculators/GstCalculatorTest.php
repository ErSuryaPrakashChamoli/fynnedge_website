<?php

use App\Support\Calculators\GstCalculator;

it('adds GST on top of a base amount, split evenly into CGST and SGST', function () {
    $result = GstCalculator::addGst(1000, 18);

    expect($result['base_amount'])->toBe(1000.0)
        ->and($result['gst_amount'])->toBe(180.0)
        ->and($result['cgst'])->toBe(90.0)
        ->and($result['sgst'])->toBe(90.0)
        ->and($result['total_amount'])->toBe(1180.0);
});

it('backs out GST from an inclusive total amount', function () {
    $result = GstCalculator::removeGst(1180, 18);

    expect($result['total_amount'])->toBe(1180.0)
        ->and($result['base_amount'])->toBe(1000.0)
        ->and($result['gst_amount'])->toBe(180.0)
        ->and($result['cgst'])->toBe(90.0)
        ->and($result['sgst'])->toBe(90.0);
});

it('round-trips an amount through addGst and removeGst', function () {
    $added = GstCalculator::addGst(2500, 12);
    $removed = GstCalculator::removeGst($added['total_amount'], 12);

    expect($removed['base_amount'])->toBe(2500.0);
});

it('returns zeros for a non-positive amount', function () {
    expect(GstCalculator::addGst(0, 18))->toBe(['base_amount' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'gst_amount' => 0.0, 'total_amount' => 0.0]);
    expect(GstCalculator::removeGst(0, 18))->toBe(['base_amount' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'gst_amount' => 0.0, 'total_amount' => 0.0]);
});
