<?php

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;
use App\Money\Currency;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->actingAs(User::factory()->entry()->create());
    $this->dentist = Dentist::create(['name' => 'د. سامي']);

    $line = fn (string $date, int $price, string $type = 'زيركون') => [
        'dentist_id' => $this->dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => $type, 'patient_name' => 'مريض', 'quantity' => 1, 'price' => $price,
            'date' => $date, 'selected_teeth' => [11],
        ]],
    ];

    $this->post(route('daybook.orders.store'), $line('2026-05-10', 100000));
    $this->post(route('daybook.orders.store'), $line('2026-06-10', 500000, 'معدن'));
    DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
});

test('the notebook lists work and payments with a running balance from the ledger opening', function () {
    $this->get(route('notebook.show', [$this->dentist, 'from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notebook/show')
            ->where('dentist.name', 'د. سامي')
            ->where('opening', 100000)
            ->where('closing', 400000)
            ->has('lines', 2)
            ->where('lines.0.kind', 'work')
            ->where('lines.0.type', 'معدن')
            ->where('lines.0.date', '2026-06-10')
            ->where('lines.0.balance', 600000)
            ->where('lines.1.kind', 'payment')
            ->where('lines.1.balance', 400000));
});

test('the whole history ends on the ledger balance', function () {
    $ledger = app(LedgerReports::class)->receivablesByDentist(null, Currency::SYP)[$this->dentist->id];

    $this->get(route('notebook.show', [$this->dentist, 'all' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('opening', 0)
            ->where('closing', $ledger)
            ->has('lines', 3)
            ->where('lines.2.balance', $ledger)
            ->where('filters.all', true));
});

test('lines still add up to the ledger for orders without items, cancelled orders and multi-date orders', function () {
    // An order with no items posts its amount on its due date.
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-20', 'amount' => 70000, 'status' => 'pending']);
    // A cancelled order posts nothing.
    Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-21', 'amount' => 999, 'status' => 'cancelled']);
    // One order, two dates.
    $this->actingAs(User::factory()->create())->post(route('orders.store'), [
        'dentist_id' => $this->dentist->id,
        'status' => 'pending',
        'items' => [
            ['type' => 'أ', 'quantity' => 1, 'price' => 1000, 'date' => '2026-06-22', 'selected_teeth' => []],
            ['type' => 'ب', 'quantity' => 3, 'price' => 2000, 'date' => '2026-07-02', 'selected_teeth' => []],
        ],
    ])->assertSessionHasNoErrors();

    $reports = app(LedgerReports::class);

    foreach ([['2026-06-01', '2026-06-30'], ['2026-07-01', '2026-07-31'], [null, null]] as [$from, $to]) {
        $statement = $reports->dentistStatement($this->dentist->id, $from, $to, Currency::SYP);
        $query = $from ? ['from' => $from, 'to' => $to] : ['all' => 1];

        $this->get(route('notebook.show', [$this->dentist, ...$query]))
            ->assertInertia(function ($page) use ($statement) {
                $lines = $page->toArray()['props']['lines'];

                expect($page->toArray()['props']['closing'])->toBe($statement['closing']);
                expect(end($lines)['balance'])->toBe($statement['closing']);
            });
    }
});

test('the notebook opens on the current month by default', function () {
    $this->get(route('notebook.show', $this->dentist))
        ->assertInertia(fn ($page) => $page
            ->where('filters.from', now()->startOfMonth()->toDateString())
            ->where('filters.to', now()->endOfMonth()->toDateString())
            ->where('filters.all', false));
});

test('a dollar dentist notebook is in cents', function () {
    $dentist = Dentist::create(['name' => 'د. دولار', 'currency' => 'USD']);

    $this->post(route('daybook.orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 2, 'currency' => 'USD', 'original_amount' => 1250, 'date' => '2026-06-15', 'selected_teeth' => []]],
    ]);
    $this->post(route('daybook.payments.store'), [
        'dentist_id' => $dentist->id, 'currency' => 'USD', 'original_amount' => 10, 'payment_date' => '2026-06-16',
    ])->assertSessionHasNoErrors();

    $this->get(route('notebook.show', [$dentist, 'all' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('dentist.currency', 'USD')
            ->where('lines.0.value', 2500)
            ->where('lines.1.value', 1000)
            ->where('closing', 1500));
});

test('the notebook index carries each dentist balance from the ledger', function () {
    $this->get(route('notebook.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notebook/index')
            ->has('dentists', 1)
            ->where('dentists.0.balance', 400000));
});

test('the notebook print view needs a valid signature', function () {
    auth()->logout();

    $this->get("/notebook/{$this->dentist->id}/print-view?all=1")->assertForbidden();

    $this->get(URL::temporarySignedRoute('notebook.print-view', now()->addMinute(), ['dentist' => $this->dentist->id, 'all' => 1], absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('notebook/print')->has('lines', 3));
});
