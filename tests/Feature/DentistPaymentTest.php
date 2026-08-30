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

test('a payment can carry a note', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. نور']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'amount' => 25000,
        'payment_date' => '2026-06-15',
        'notes' => 'استلمت نقداً من السكرتيرة عن طلبات شهر ٧',
    ])->assertSessionHasNoErrors();

    expect(DentistPayment::sole()->notes)
        ->toBe('استلمت نقداً من السكرتيرة عن طلبات شهر ٧');
});

test('a note is optional', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. بلا ملاحظة']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'amount' => 25000,
        'payment_date' => '2026-06-15',
    ])->assertSessionHasNoErrors();

    expect(DentistPayment::sole()->notes)->toBeNull();
});

test("a dollar dentist's payment keeps its note", function () {
    // A native dollar payment submits a whitelist of keys — `amount` and
    // `rate` are `prohibited` for this dentist — so the note has to survive
    // a payload shaped by moneyPayload()'s native branch, not just the lira
    // one.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. دولار', 'currency' => 'USD']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'payment_date' => '2026-06-15',
        'currency' => 'USD',
        'original_amount' => '200',
        'notes' => 'حوالة',
    ])->assertSessionHasNoErrors();

    $payment = DentistPayment::sole();
    expect($payment->notes)->toBe('حوالة')
        ->and($payment->original_amount)->toBe(200_00);
});

test('a note can be edited', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. تعديل']);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 10000,
        'payment_date' => '2026-06-01',
        'notes' => 'نقداً',
    ]);

    $this->put(route('payments.update', $payment), [
        'dentist_id' => $dentist->id,
        'amount' => 10000,
        'payment_date' => '2026-06-01',
        'notes' => 'شيك رقم ٤٤٢',
    ])->assertSessionHasNoErrors();

    expect($payment->refresh()->notes)->toBe('شيك رقم ٤٤٢');
});
