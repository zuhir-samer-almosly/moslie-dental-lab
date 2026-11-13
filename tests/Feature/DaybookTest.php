<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->actingAs(User::factory()->entry()->create());
    $this->dentist = Dentist::create(['name' => 'د. سامر']);
});

function workLine(Dentist $dentist, array $item = [], array $extra = []): array
{
    return [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون',
            'patient_name' => 'أحمد',
            'quantity' => 2,
            'price' => 50000,
            'date' => '2026-06-15',
            'selected_teeth' => [11, 12],
            ...$item,
        ]],
        ...$extra,
    ];
}

test('a work line is saved as its own order and posts to the ledger', function () {
    $this->from(route('daybook.index', ['date' => '2026-06-15']))
        ->post(route('daybook.orders.store'), workLine($this->dentist))
        ->assertRedirect(route('daybook.index', ['date' => '2026-06-15']))
        ->assertSessionHasNoErrors();

    $order = Order::with('items')->sole();
    expect($order->amount)->toBe(100000);
    expect($order->items)->toHaveCount(1);
    expect($order->items[0]->meta['patient_name'])->toBe('أحمد');

    expect((int) JournalLine::sum('debit'))->toBe(100000);
    expect((int) JournalLine::sum('credit'))->toBe(100000);
});

test('a payment line is saved and sends the user back to the daybook', function () {
    $this->from(route('daybook.index'))
        ->post(route('daybook.payments.store'), [
            'dentist_id' => $this->dentist->id,
            'amount' => 25000,
            'payment_date' => '2026-06-15',
            'notes' => 'نقداً',
        ])
        ->assertRedirect(route('daybook.index'));

    expect(DentistPayment::sole()->amount)->toBe(25000);
});

test('the daybook shows only the lines of its own day, in entry order', function () {
    $this->post(route('daybook.orders.store'), workLine($this->dentist));
    $this->post(route('daybook.orders.store'), workLine($this->dentist, ['date' => '2026-06-16', 'type' => 'معدن']));
    $this->post(route('daybook.payments.store'), [
        'dentist_id' => $this->dentist->id, 'amount' => 25000, 'payment_date' => '2026-06-15',
    ]);

    $this->get(route('daybook.index', ['date' => '2026-06-15']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('daybook/index')
            ->where('date', '2026-06-15')
            ->has('lines', 2)
            ->where('lines.0.kind', 'work')
            ->where('lines.0.type', 'زيركون')
            ->where('lines.0.value', 100000)
            ->where('lines.0.editable', true)
            ->where('lines.0.selected_teeth', [11, 12])
            ->where('lines.1.kind', 'payment')
            ->where('lines.1.value', 25000)
            ->has('dentists', 1)
            ->has('workTypes.catalog'));
});

test('a line of a larger order shows on its day but is not editable from the daybook', function () {
    $payload = workLine($this->dentist);
    $payload['items'][] = [...$payload['items'][0], 'date' => '2026-06-16', 'type' => 'معدن'];

    $this->post(route('daybook.orders.store'), $payload)->assertSessionHasNoErrors();

    $this->get(route('daybook.index', ['date' => '2026-06-16']))
        ->assertInertia(fn ($page) => $page
            ->has('lines', 1)
            ->where('lines.0.type', 'معدن')
            ->where('lines.0.editable', false));
});

test('cancelled work is left off the daybook', function () {
    $this->post(route('daybook.orders.store'), workLine($this->dentist, [], ['status' => 'cancelled']));

    $this->get(route('daybook.index', ['date' => '2026-06-15']))
        ->assertInertia(fn ($page) => $page->has('lines', 0));
});

test('an invalid date falls back to today', function () {
    $this->get(route('daybook.index', ['date' => 'garbage']))
        ->assertInertia(fn ($page) => $page->where('date', now()->toDateString()));
});

test('retrying a save with the same entry token records the line once', function () {
    $payload = workLine($this->dentist, [], ['entry_token' => 'token-1']);

    $this->post(route('daybook.orders.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('daybook.orders.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Order::count())->toBe(1);

    // A new token is a new line.
    $this->post(route('daybook.orders.store'), [...$payload, 'entry_token' => 'token-2']);
    expect(Order::count())->toBe(2);
});

test('the same entry token cannot swallow a payment either', function () {
    $payload = [
        'dentist_id' => $this->dentist->id, 'amount' => 25000, 'payment_date' => '2026-06-15', 'entry_token' => 'pay-1',
    ];

    $this->post(route('daybook.payments.store'), $payload);
    $this->post(route('daybook.payments.store'), $payload);

    expect(DentistPayment::count())->toBe(1);
});

test('a save that failed validation releases its token for the corrected retry', function () {
    $bad = workLine($this->dentist, ['type' => ''], ['entry_token' => 'token-1']);

    $this->post(route('daybook.orders.store'), $bad)->assertSessionHasErrors('items.0.type');
    expect(Order::count())->toBe(0);

    $this->post(route('daybook.orders.store'), workLine($this->dentist, [], ['entry_token' => 'token-1']))
        ->assertSessionHasNoErrors();
    expect(Order::count())->toBe(1);
});

test('a dollar dentist line is saved and shown in cents', function () {
    $dentist = Dentist::create(['name' => 'د. دولار', 'currency' => 'USD']);

    $this->post(route('daybook.orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => [[
            'type' => 'زيركون', 'quantity' => 2, 'currency' => 'USD', 'original_amount' => 1250,
            'date' => '2026-06-15', 'selected_teeth' => [],
        ]],
    ])->assertSessionHasNoErrors();

    $this->get(route('daybook.index', ['date' => '2026-06-15']))
        ->assertInertia(fn ($page) => $page
            ->where('lines.0.currency', 'USD')
            ->where('lines.0.value', 2500));
});

test('a line can be edited and deleted from the daybook', function () {
    $this->post(route('daybook.orders.store'), workLine($this->dentist));
    $order = Order::sole();

    $this->from(route('daybook.index'))
        ->put(route('daybook.orders.update', $order), workLine($this->dentist, ['price' => 60000]))
        ->assertRedirect(route('daybook.index'));
    expect($order->fresh()->amount)->toBe(120000);
    expect((int) JournalLine::sum('debit'))->toBe(120000);

    $this->delete(route('daybook.orders.destroy', $order))->assertRedirect();
    expect(Order::count())->toBe(0);
    expect(JournalLine::count())->toBe(0);
});

test('a payment can be edited and deleted from the daybook', function () {
    $payment = DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 25000, 'payment_date' => '2026-06-15']);

    $this->put(route('daybook.payments.update', $payment), [
        'dentist_id' => $this->dentist->id, 'amount' => 30000, 'payment_date' => '2026-06-15',
    ])->assertRedirect(route('daybook.index'));
    expect($payment->fresh()->amount)->toBe(30000);

    $this->delete(route('daybook.payments.destroy', $payment));
    expect(DentistPayment::count())->toBe(0);
});

test('the ordinary order and payment pages still redirect to their own lists', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.store'), workLine($this->dentist))->assertRedirect(route('orders.index'));
    $this->post(route('payments.store'), [
        'dentist_id' => $this->dentist->id, 'amount' => 1, 'payment_date' => '2026-06-15',
    ])->assertRedirect(route('payments.index'));
});

test('the daybook print view needs a valid signature', function () {
    $this->post(route('daybook.orders.store'), workLine($this->dentist));
    auth()->logout();

    $this->get('/daybook/print-view?date=2026-06-15')->assertForbidden();

    $this->get(URL::temporarySignedRoute('daybook.print-view', now()->addMinute(), ['date' => '2026-06-15'], absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('daybook/print')->has('lines', 1));
});
