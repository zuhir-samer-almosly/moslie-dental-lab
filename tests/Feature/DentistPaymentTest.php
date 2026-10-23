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

test('a payment requires a dentist, a non-negative amount and a date', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('payments.store'), [
        'dentist_id' => null,
        'amount' => -1,
        'payment_date' => 'not-a-date',
    ])->assertSessionHasErrors(['dentist_id', 'amount', 'payment_date']);

    expect(DentistPayment::count())->toBe(0);
});

test('a payment amount can be zero', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. مجاناً']);

    $this->post(route('payments.store'), [
        'dentist_id' => $dentist->id,
        'amount' => 0,
        'payment_date' => '2026-06-15',
    ])->assertRedirect(route('payments.index'))->assertSessionHasNoErrors();

    expect(DentistPayment::sole()->amount)->toBe(0);
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

test('several payments from one dentist can be recorded at once', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. شيكات']);

    $this->post(route('payments.store-many'), [
        'dentist_id' => $dentist->id,
        'payments' => [
            ['amount' => 10000, 'payment_date' => '2026-10-20', 'notes' => 'شيك ١'],
            ['amount' => 20000, 'payment_date' => '2026-10-21', 'notes' => 'شيك ٢'],
            ['currency' => 'USD', 'original_amount' => '50', 'rate' => '130', 'payment_date' => '2026-10-22'],
        ],
    ])->assertRedirect(route('payments.index'))->assertSessionHasNoErrors();

    $payments = DentistPayment::orderBy('id')->get();
    expect($payments)->toHaveCount(3)
        ->and($payments->pluck('dentist_id')->unique()->all())->toBe([$dentist->id])
        ->and($payments->pluck('amount')->all())->toBe([10000, 20000, 6500])
        ->and($payments->pluck('payment_date')->all())->toBe(['2026-10-20', '2026-10-21', '2026-10-22'])
        ->and($payments[0]->notes)->toBe('شيك ١')
        ->and($payments[2]->original_amount)->toBe(50_00);
});

test('one bad row saves none of the batch', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. خطأ']);

    $this->post(route('payments.store-many'), [
        'dentist_id' => $dentist->id,
        'payments' => [
            ['amount' => 10000, 'payment_date' => '2026-10-20'],
            ['amount' => -5, 'payment_date' => 'not-a-date'],
        ],
    ])->assertSessionHasErrors(['payments.1.amount', 'payments.1.payment_date'])
        ->assertSessionDoesntHaveErrors(['payments.0.amount']);

    expect(DentistPayment::count())->toBe(0);
});

test('a batch needs a dentist and at least one payment', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('payments.store-many'), [
        'dentist_id' => null,
        'payments' => [],
    ])->assertSessionHasErrors(['dentist_id', 'payments']);
});

test("a dollar dentist's batch is native dollars", function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. دولارات', 'currency' => 'USD']);

    $this->post(route('payments.store-many'), [
        'dentist_id' => $dentist->id,
        'payments' => [
            ['currency' => 'USD', 'original_amount' => '200', 'payment_date' => '2026-10-20'],
            ['currency' => 'USD', 'original_amount' => '75.50', 'payment_date' => '2026-10-21', 'notes' => 'حوالة'],
        ],
    ])->assertSessionHasNoErrors();

    $payments = DentistPayment::orderBy('id')->get();
    expect($payments->pluck('original_amount')->all())->toBe([200_00, 75_50])
        ->and($payments->pluck('rate')->filter()->all())->toBe([])
        ->and($payments[1]->notes)->toBe('حوالة');

    // A rate on a dollar dentist's row is refused, exactly as for one payment.
    $this->post(route('payments.store-many'), [
        'dentist_id' => $dentist->id,
        'payments' => [
            ['currency' => 'USD', 'original_amount' => '10', 'rate' => '130', 'payment_date' => '2026-10-20'],
        ],
    ])->assertSessionHasErrors(['payments.0.rate']);
});

test('batch errors name the field in Arabic, not by its array key', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. رسائل']);

    $this->post(route('payments.store-many'), [
        'dentist_id' => $dentist->id,
        'payments' => [['amount' => '', 'payment_date' => '']],
    ])->assertSessionHasErrors([
        'payments.0.amount' => 'حقل المبلغ مطلوب.',
    ]);

    expect(session('errors')->first('payments.0.payment_date'))
        ->toContain('التاريخ')
        ->not->toContain('payments.0');
});
