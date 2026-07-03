<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\User;

test('a dentist payment can be recorded', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'amount' => 25000,
        'payment_date' => '2026-06-15',
    ])->assertRedirect(route('payments.index'));

    $payment = DentistPayment::sole();
    expect($payment->dentist_id)->toBe($dentist->id);
    expect($payment->amount)->toBe(25000);
    expect($payment->payment_date)->toBe('2026-06-15');
});

test('a payment requires a dentist, a positive amount and a date', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('payments.store'), [
        'dentist_id' => null,
        'amount' => 0,
        'payment_date' => 'not-a-date',
    ])->assertSessionHasErrors(['dentist_id', 'amount', 'payment_date']);

    expect(DentistPayment::count())->toBe(0);
});

test('a dentist payment can be updated', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. علي']);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 10000,
        'payment_date' => '2026-06-01',
    ]);

    $this->put(route('payments.update', $payment), [
        'dentist_id' => $dentist->id,
        'amount' => 15000,
        'payment_date' => '2026-06-02',
    ])->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->amount)->toBe(15000);
    expect($payment->payment_date)->toBe('2026-06-02');
});

test('a dentist payment can be deleted', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. حذف']);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 5000,
        'payment_date' => '2026-06-01',
    ]);

    $this->delete(route('payments.destroy', $payment))->assertRedirect(route('payments.index'));

    expect(DentistPayment::find($payment->id))->toBeNull();
});

test('guests cannot access the payments page', function () {
    $this->get(route('payments.index'))->assertRedirect(route('login'));
});
