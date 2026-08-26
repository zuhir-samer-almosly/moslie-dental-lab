<?php

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\User;

test('a dollar payment stores the lira it converted to', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => 100_00,
        'rate' => '13',
    ]);

    expect($payment->amount)->toBe(1300);
});

test('the ledger posts a dollar payment at its lira value', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => 100_00,
        'rate' => '13',
    ]);

    expect(app(LedgerReports::class)->balance(AccountCode::CASH->value))->toBe(1300);
});

test('a dollar payment with no rate is refused rather than booked as nothing', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    expect(fn () => DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => 100_00,
    ]))->toThrow(\App\Money\MissingRateException::class);
});

test('a dollar payment submitted through the form is stored in lira', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '100.50',
        'rate' => '13',
    ])->assertRedirect(route('payments.index'));

    // $100.50 x 13 = 1306.5, rounded to the nearest lira.
    expect(DentistPayment::sole())
        ->amount->toBe(1307)
        ->original_amount->toBe(10050)
        ->currency->toBe('USD');
});

test('a lira payment still needs no currency field at all', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'amount' => 25000,
    ])->assertRedirect(route('payments.index'));

    expect(DentistPayment::sole())
        ->amount->toBe(25000)
        ->currency->toBe('SYP')
        ->rate->toBeNull();
});

test('a dollar payment without a rate is rejected by validation', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '100',
    ])->assertSessionHasErrors('rate');

    expect(DentistPayment::count())->toBe(0);
});

test('the rate typed on a dollar payment becomes that day\'s rate', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '100',
        'rate' => '13',
    ]);

    expect(\App\Money\Rate::on('2026-08-02'))->toBe('13.000000');
});

test('a later rate for the same day replaces the remembered one', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    foreach (['13', '13.5'] as $rate) {
        $this->post(route('payments.store'), [
            'dentist_id' => $dentist->id,
            'payment_date' => '2026-08-02',
            'currency' => 'USD',
            'original_amount' => '100',
            'rate' => $rate,
        ]);
    }

    expect(\App\Money\Rate::on('2026-08-02'))->toBe('13.500000')
        ->and(\App\Models\ExchangeRate::count())->toBe(1);
});

test('a lira payment records no rate', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'amount' => 25000,
    ]);

    expect(\App\Models\ExchangeRate::count())->toBe(0);
});

test('a lira payment can be edited into a dollar one', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'amount' => 25000,
    ]);

    $this->put(route('payments.update', $payment), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '100',
        'rate' => '13',
    ])->assertRedirect(route('payments.index'));

    expect($payment->fresh())
        ->amount->toBe(1300)
        ->currency->toBe('USD')
        ->original_amount->toBe(10000);
});

test('a dollar payment edited back to lira drops its conversion', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => 100_00,
        'rate' => '13',
    ]);

    $this->put(route('payments.update', $payment), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'amount' => 25000,
    ])->assertRedirect(route('payments.index'));

    expect($payment->fresh())
        ->amount->toBe(25000)
        ->currency->toBe('SYP')
        ->original_amount->toBeNull()
        ->rate->toBeNull();
});

test('editing a dollar payment re-posts the ledger at the new lira value', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => 100_00,
        'rate' => '13',
    ]);

    $this->put(route('payments.update', $payment), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '100',
        'rate' => '14',
    ]);

    expect(app(LedgerReports::class)->balance(AccountCode::CASH->value))->toBe(1400);
});

test('a zeroed dollar placeholder on a lira payment is ignored, not rejected', function () {
    // The order form sends 0 for the dollar fields on a lira line while the
    // payment form sends ''. That difference was accidental, and the 0 shape
    // broke order saving outright. The rules should not care either way:
    // on a lira row these fields describe nothing.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-08-02',
        'amount' => 25000,
        'currency' => 'SYP',
        'original_amount' => 0,
        'rate' => 0,
    ])->assertRedirect(route('payments.index'))->assertSessionHasNoErrors();

    expect(DentistPayment::sole())
        ->amount->toBe(25000)
        ->currency->toBe('SYP')
        ->original_amount->toBeNull()
        ->rate->toBeNull();
});
