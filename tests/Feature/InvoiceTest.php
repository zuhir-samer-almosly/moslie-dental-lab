<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;

function makeOrder(int $dentistId, int $amount, string $createdAt): void
{
    $order = Order::create([
        'dentist_id' => $dentistId,
        'due_date' => $createdAt,
        'amount' => $amount,
        'status' => 'pending',
    ]);
    $order->forceFill(['created_at' => $createdAt])->save();
}

function makePayment(int $dentistId, int $amount, string $createdAt): void
{
    $payment = DentistPayment::create([
        'dentist_id' => $dentistId,
        'amount' => $amount,
        'payment_date' => $createdAt,
    ]);
    $payment->forceFill(['created_at' => $createdAt])->save();
}

test('the invoice carries forward an unpaid balance from earlier months', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامر']);

    // Last month: ordered 100,000, only paid 40,000 → 60,000 still owed.
    makeOrder($dentist->id, 100000, '2026-05-10');
    makePayment($dentist->id, 40000, '2026-05-20');

    // This month: new order 50,000, paid 20,000.
    makeOrder($dentist->id, 50000, '2026-06-12');
    makePayment($dentist->id, 20000, '2026-06-25');

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('invoices/index')
                ->where('totals.opening', 60000)
                ->where('totals.orders', 50000)
                ->where('totals.payments', 20000)
                ->where('totals.balance', 90000)
                ->where("openingByDentist.{$dentist->id}", 60000)
        );
});

test('a dentist with a fully settled history has no opening balance', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. علي']);
    makeOrder($dentist->id, 30000, '2026-05-10');
    makePayment($dentist->id, 30000, '2026-05-15');

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('totals.opening', 0)
                ->where('openingByDentist', [])
        );
});

test('cancelled orders are excluded from invoice totals and opening balance', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. ملغى']);

    // Prior month: a real order and a cancelled one — only the real 30,000
    // should carry forward as opening balance.
    makeOrder($dentist->id, 30000, '2026-05-10');
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-11', 'amount' => 99000, 'status' => 'cancelled']);

    // This month: a real order and a cancelled one — totals.orders is 20,000.
    makeOrder($dentist->id, 20000, '2026-06-12');
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-13', 'amount' => 77000, 'status' => 'cancelled']);

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('totals.opening', 30000)
                ->where('totals.orders', 20000)
                ->where('totals.balance', 50000)
        );
});

test('invoice opening balances are the ledger receivable before the period, not recomputed from domain tables', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    // Before the period: 300,000 billed, 100,000 paid → 200,000 carried in.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-10', 'amount' => 300000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 100000, 'payment_date' => '2026-05-20']);
    // In the period.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('totals.opening', 200000)
            ->where('openingByDentist.'.$dentist->id, 200000)
        );

    // Prove the number is read from the ledger, not recomputed: wipe the
    // ledger and the page must report zero, even though the domain rows
    // (order, payment) are untouched.
    \App\Models\JournalEntry::query()->delete();

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('totals.opening', 0)
            ->where('openingByDentist', [])
        );

    expect(Order::count())->toBe(2);
    expect(DentistPayment::count())->toBe(1);
});

test('invoice opening balance boundary is the day before the period, not the first day of it', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. حدّي']);
    // Exactly the day before the period starts: must carry into opening.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-31', 'amount' => 70000, 'status' => 'pending']);
    // Exactly the first day of the period: must NOT be opening — it belongs
    // to this period's own orders total.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-01', 'amount' => 40000, 'status' => 'pending']);

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('totals.opening', 70000)
            ->where('openingByDentist.'.$dentist->id, 70000)
            ->where('totals.orders', 40000)
        );
});

test('invoice opening totals and the per-dentist map agree, and a credit balance is not clamped to zero', function () {
    $this->actingAs(User::factory()->create());

    // Owes money going into the period.
    $ower = Dentist::create(['name' => 'د. مدين']);
    Order::create(['dentist_id' => $ower->id, 'due_date' => '2026-05-10', 'amount' => 300000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $ower->id, 'amount' => 100000, 'payment_date' => '2026-05-15']);

    // Overpaid before the period — a legitimate negative (credit) balance.
    $overpaid = Dentist::create(['name' => 'د. دائن']);
    Order::create(['dentist_id' => $overpaid->id, 'due_date' => '2026-05-10', 'amount' => 100000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $overpaid->id, 'amount' => 150000, 'payment_date' => '2026-05-15']);

    $this->get(route('invoices.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('openingByDentist.'.$ower->id, 200000)
            ->where('openingByDentist.'.$overpaid->id, -50000)
            ->where('totals.opening', 150000)
        );
});

test('guests cannot access invoices', function () {
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
});

test('guests cannot download the invoice pdf', function () {
    $this->get(route('invoices.pdf'))->assertRedirect(route('login'));
});

test('the pdf needs a valid period before it will render', function () {
    $this->actingAs(User::factory()->create());

    // No dates → nothing to bill. This has to short-circuit before Browsershot
    // is ever booted, which is also what keeps this test fast.
    $this->get(route('invoices.pdf'))->assertStatus(422);
});

test('the print view rejects an unsigned request', function () {
    $this->get('/invoices/print-view?from=2026-06-01&to=2026-06-30')
        ->assertForbidden();
});

test('the print view renders for a signed url without a session', function () {
    $dentist = Dentist::create(['name' => 'د. سامر']);
    makeOrder($dentist->id, 50000, '2026-06-12');
    makePayment($dentist->id, 20000, '2026-06-25');

    // Signed relatively, exactly as InvoiceController::pdf mints it — headless
    // Chromium fetches this with no cookies at all.
    $path = URL::temporarySignedRoute(
        'invoices.print-view',
        now()->addMinutes(2),
        ['from' => '2026-06-01', 'to' => '2026-06-30'],
        absolute: false,
    );

    $this->get($path)
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('invoices/print')
                ->where('totals.orders', 50000)
                ->where('totals.payments', 20000)
                ->where('totals.balance', 30000)
        );
});

test('the print view rejects an expired signature', function () {
    $path = URL::temporarySignedRoute(
        'invoices.print-view',
        now()->addMinutes(2),
        ['from' => '2026-06-01', 'to' => '2026-06-30'],
        absolute: false,
    );

    $this->travel(3)->minutes();

    $this->get($path)->assertForbidden();
});
