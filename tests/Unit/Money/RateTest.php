<?php

use App\Money\Rate;

test('converts dollars to lira at the given rate', function () {
    expect(Rate::toSyp(100_00, '13'))->toBe(1300);
});

test('rounds to the nearest lira', function () {
    // $17.50 x 13.5 = 236.25
    expect(Rate::toSyp(17_50, '13.5'))->toBe(236);
});

test('rounds a half lira up', function () {
    // $0.50 x 13 = 6.5
    expect(Rate::toSyp(50, '13'))->toBe(7);
});

test('converts zero to zero', function () {
    expect(Rate::toSyp(0, '13'))->toBe(0);
});

test('keeps six decimal places of the rate', function () {
    // $1,000,000 x 0.000001 = 1
    expect(Rate::toSyp(1_000_000_00, '0.000001'))->toBe(1);
});
