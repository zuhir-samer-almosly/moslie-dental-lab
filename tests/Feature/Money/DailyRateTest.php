<?php

use App\Models\Dentist;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Money\Rate;
use Illuminate\Support\Facades\DB;

test('setting the rate records it for today, flagged as set by hand', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('exchange-rate.store'), ['rate' => '11500'])
        ->assertRedirect();

    $row = ExchangeRate::sole();

    expect($row->rate)->toBe('11500.000000')
        ->and($row->rate_date->toDateString())->toBe(now()->toDateString())
        ->and($row->is_manual)->toBeTrue();
});

test('setting the rate twice in a day updates the same row', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('exchange-rate.store'), ['rate' => '11500']);
    $this->post(route('exchange-rate.store'), ['rate' => '11600'])
        ->assertRedirect();

    expect(ExchangeRate::count())->toBe(1)
        ->and(ExchangeRate::sole()->rate)->toBe('11600.000000');
});

test('an order typed at another rate does not redefine the day the owner set', function () {
    // The whole point of the control: one order agreed at an odd rate used to
    // become the rate every invoice for that day was read back through.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('exchange-rate.store'), ['rate' => '11500']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'خزف',
            'quantity' => 1,
            'date' => now()->toDateString(),
            'currency' => 'USD',
            'original_amount' => 17_00,
            'rate' => '11600',
            'selected_teeth' => [],
        ]],
    ])->assertRedirect(route('orders.index'));

    // The order keeps the rate it was agreed at...
    expect(\App\Models\Order::with('items')->sole()->items->first()->price)
        ->toBe(197_200);
    // ...but the day stays priced at what the owner set.
    expect(Rate::on(now()->toDateString()))->toBe('11500.000000');
});

test('a remembered rate is still overwritten by a later entry', function () {
    // Only a hand-set day is protected. A day whose rate merely came from an
    // entry keeps behaving as before: the newest entry defines it.
    Rate::remember('2026-08-01', '13');
    Rate::remember('2026-08-01', '14');

    expect(Rate::on('2026-08-01'))->toBe('14.000000')
        ->and(ExchangeRate::sole()->is_manual)->toBeFalse();
});

test('a hand-set rate is protected on its own day only', function () {
    Rate::set('2026-08-01', '13');
    Rate::remember('2026-08-02', '14');

    expect(Rate::on('2026-08-01'))->toBe('13.000000')
        ->and(Rate::on('2026-08-02'))->toBe('14.000000');
});

test('the day a rate is set by hand is stored as a bare date', function () {
    // Same trap as elsewhere: a stored time component makes the string-bound
    // `<=` in Rate::on miss a rate dated exactly on the boundary.
    Rate::set('2026-08-01 15:30:00', '13');

    expect(DB::table('exchange_rates')->value('rate_date'))->toBe('2026-08-01');
});

test('whether a day has a rate of its own is reported for either route', function () {
    Rate::set('2026-08-01', '13');
    Rate::remember('2026-08-03', '14');

    expect(Rate::isRecordedOn('2026-08-01'))->toBeTrue()
        ->and(Rate::isRecordedOn('2026-08-03'))->toBeTrue()
        // 8/2 inherits 8/1's rate, but it did not record one.
        ->and(Rate::isRecordedOn('2026-08-02'))->toBeFalse()
        ->and(Rate::isRecordedOn(null))->toBeFalse();
});

test('a rate that is not a positive number is rejected', function (mixed $rate) {
    $this->actingAs(User::factory()->create());

    $this->post(route('exchange-rate.store'), ['rate' => $rate])
        ->assertSessionHasErrors('rate');

    expect(ExchangeRate::count())->toBe(0);
})->with([
    'zero' => ['0'],
    'negative' => ['-1'],
    'not a number' => ['abc'],
    'empty' => [''],
]);

test('a guest cannot set the rate', function () {
    $this->post(route('exchange-rate.store'), ['rate' => '11500'])
        ->assertRedirect(route('login'));

    expect(ExchangeRate::count())->toBe(0);
});

test('the sidebar is given the day rate and whether today recorded one', function () {
    $this->actingAs(User::factory()->create());
    Rate::set(now()->subDay()->toDateString(), '11500');

    $this->get(route('dashboard'))->assertInertia(
        fn ($page) => $page
            ->where('dailyRate.rate', '11500.000000')
            ->where('dailyRate.recorded_today', false)
    );

    $this->post(route('exchange-rate.store'), ['rate' => '11600']);

    $this->get(route('dashboard'))->assertInertia(
        fn ($page) => $page
            ->where('dailyRate.rate', '11600.000000')
            ->where('dailyRate.recorded_today', true)
    );
});
