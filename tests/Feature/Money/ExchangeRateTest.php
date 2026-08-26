<?php

use App\Models\ExchangeRate;
use App\Money\Rate;

test('the rate in effect on a date is the newest one set on or before it', function () {
    ExchangeRate::create(['rate_date' => '2026-08-01', 'rate' => '13']);
    ExchangeRate::create(['rate_date' => '2026-08-20', 'rate' => '14']);

    expect(Rate::on('2026-08-05'))->toBe('13.000000');
});

test('a rate set on the exact date is the one used', function () {
    ExchangeRate::create(['rate_date' => '2026-08-01', 'rate' => '13']);
    ExchangeRate::create(['rate_date' => '2026-08-20', 'rate' => '14']);

    expect(Rate::on('2026-08-20'))->toBe('14.000000');
});

test('a rate set after the date is never used', function () {
    // The freeze guarantee: money booked on 8/2 must not be re-priced by a
    // rate the owner records later, however much the lira moves afterwards.
    ExchangeRate::create(['rate_date' => '2026-08-20', 'rate' => '14']);

    expect(Rate::on('2026-08-02'))->toBeNull();
});

test('a date before every recorded rate has no rate', function () {
    ExchangeRate::create(['rate_date' => '2026-08-01', 'rate' => '13']);

    expect(Rate::on('2026-07-31'))->toBeNull();
});

test('there is no rate at all when none has ever been recorded', function () {
    expect(Rate::on('2026-08-05'))->toBeNull();
});

test('a rate is stored as a bare date with no time component', function () {
    // Mirrors JournalEntry: a stored time component makes string-bound `<=`
    // comparisons miss a rate dated exactly on the boundary.
    ExchangeRate::create(['rate_date' => '2026-08-01 15:30:00', 'rate' => '13']);

    expect(\Illuminate\Support\Facades\DB::table('exchange_rates')->value('rate_date'))
        ->toBe('2026-08-01');
});

test('only one rate can be recorded per day', function () {
    ExchangeRate::create(['rate_date' => '2026-08-01', 'rate' => '13']);

    expect(fn () => ExchangeRate::create(['rate_date' => '2026-08-01', 'rate' => '14']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('asking for the rate on no date at all gives no rate', function () {
    // Reports can be opened with no period chosen yet; that must not explode
    // before their own validation has answered.
    ExchangeRate::create(['rate_date' => '2026-08-01', 'rate' => '13']);

    expect(Rate::on(null))->toBeNull();
});
