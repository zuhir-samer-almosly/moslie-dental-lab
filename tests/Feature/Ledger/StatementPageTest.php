<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->dentist = Dentist::create(['name' => 'د. سامي']);
    $this->mayOrder = Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-05-10', 'amount' => 100000, 'status' => 'pending']);
    $this->juneOrder = Order::create(['dentist_id' => $this->dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    $this->payment = DentistPayment::create(['dentist_id' => $this->dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
});

test('the statement carries an opening balance and a running balance', function () {
    $this->get(route('ledger.statement', [
        'dentist_id' => $this->dentist->id,
        'from' => '2026-06-01',
        'to' => '2026-06-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/statement')
            ->where('statement.opening', 100000)
            ->where('statement.closing', 400000)
            ->has('statement.lines', 2)
            ->where('statement.lines.0.balance', 600000)
            ->where('statement.lines.1.balance', 400000)
        );
});

test('the statement lines are pinned to exact date, description and side', function () {
    $this->get(route('ledger.statement', [
        'dentist_id' => $this->dentist->id,
        'from' => '2026-06-01',
        'to' => '2026-06-30',
    ]))
        ->assertInertia(fn ($page) => $page
            ->where('statement.lines.0.date', '2026-06-10')
            ->where('statement.lines.0.description', "طلب #{$this->juneOrder->id}")
            ->where('statement.lines.0.debit', 500000)
            ->where('statement.lines.0.credit', 0)
            ->where('statement.lines.0.balance', 600000)
            ->where('statement.lines.1.date', '2026-06-15')
            ->where('statement.lines.1.description', "دفعة #{$this->payment->id}")
            ->where('statement.lines.1.debit', 0)
            ->where('statement.lines.1.credit', 200000)
            ->where('statement.lines.1.balance', 400000)
        );
});

test('the statement page renders with no dentist selected', function () {
    $this->get(route('ledger.statement'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/statement')
            ->where('statement', null)
            ->where('dentist', null)
            ->has('dentists', 1)
        );
});

test('the statement is scoped to the requested dentist and does not leak into another dentist opening or lines', function () {
    $other = Dentist::create(['name' => 'د. آخر']);
    Order::create(['dentist_id' => $other->id, 'due_date' => '2026-05-15', 'amount' => 999000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $other->id, 'amount' => 333000, 'payment_date' => '2026-06-20']);

    // The original dentist's statement is untouched by the other dentist's rows.
    $this->get(route('ledger.statement', [
        'dentist_id' => $this->dentist->id,
        'from' => '2026-06-01',
        'to' => '2026-06-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statement.opening', 100000)
            ->has('statement.lines', 2)
            ->where('statement.closing', 400000)
        );

    // The other dentist's own statement carries only their own rows.
    $this->get(route('ledger.statement', [
        'dentist_id' => $other->id,
        'from' => '2026-06-01',
        'to' => '2026-06-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statement.opening', 999000)
            ->has('statement.lines', 1)
            ->where('statement.lines.0.credit', 333000)
            ->where('statement.closing', 666000)
        );
});

test('the opening balance moves across the from boundary by exactly one day', function () {
    // `from` equal to the order's own date: the order falls inside the period
    // (entry_date >= from), not before it, so it must NOT be in the opening.
    $this->get(route('ledger.statement', [
        'dentist_id' => $this->dentist->id,
        'from' => '2026-05-10',
        'to' => '2026-06-30',
    ]))
        ->assertInertia(fn ($page) => $page->where('statement.opening', 0));

    // One day later: the order now falls strictly before `from`, so it must
    // be folded into the opening balance instead.
    $this->get(route('ledger.statement', [
        'dentist_id' => $this->dentist->id,
        'from' => '2026-05-11',
        'to' => '2026-06-30',
    ]))
        ->assertInertia(fn ($page) => $page->where('statement.opening', 100000));
});

test('the to filter bounds the lines and the closing balance', function () {
    $this->get(route('ledger.statement', [
        'dentist_id' => $this->dentist->id,
        'from' => '2026-06-01',
        'to' => '2026-06-10',
    ]))
        ->assertInertia(fn ($page) => $page
            ->where('statement.opening', 100000)
            ->has('statement.lines', 1)
            ->where('statement.lines.0.date', '2026-06-10')
            ->where('statement.closing', 600000)
        );
});

test('a dentist with no ledger activity gets an empty statement, not a crash', function () {
    $empty = Dentist::create(['name' => 'د. فارغ']);

    $this->get(route('ledger.statement', ['dentist_id' => $empty->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statement.opening', 0)
            ->where('statement.closing', 0)
            ->has('statement.lines', 0)
        );
});

test('a nonexistent dentist_id does not crash the statement page', function () {
    $this->get(route('ledger.statement', ['dentist_id' => 999999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statement', null)
            ->where('dentist', null)
        );
});

test('the pdf refuses to render without a dentist selected', function () {
    $this->get(route('ledger.statement.pdf'))->assertStatus(422);
});

test('the pdf refuses to render for a nonexistent dentist', function () {
    $this->get(route('ledger.statement.pdf', ['dentist_id' => 999999]))->assertStatus(422);
});

test('the print view is reachable only through a signed url', function () {
    auth()->logout();

    $this->get(route('ledger.statement.print-view', ['dentist_id' => $this->dentist->id]))
        ->assertForbidden();

    $signed = URL::temporarySignedRoute(
        'ledger.statement.print-view',
        now()->addMinutes(2),
        ['dentist_id' => $this->dentist->id],
        absolute: false,
    );

    $this->get($signed)->assertOk();
});

test('the print view renders the statement component with the right data', function () {
    $signed = URL::temporarySignedRoute(
        'ledger.statement.print-view',
        now()->addMinutes(2),
        ['dentist_id' => $this->dentist->id],
        absolute: false,
    );

    $this->get($signed)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/statement-print')
            ->where('dentist.id', $this->dentist->id)
            ->where('statement.opening', 0)
            ->has('statement.lines', 3)
            ->where('statement.closing', 400000)
        );
});

test('the print view does not carry the full dentist roster', function () {
    // The picker list is only for the authenticated page's <select>; the
    // print-view/PDF path is reached over a signed URL gated by nothing but
    // the signature, so every other dentist's name has no business riding
    // along in that payload.
    Dentist::create(['name' => 'د. آخر']);

    $signed = URL::temporarySignedRoute(
        'ledger.statement.print-view',
        now()->addMinutes(2),
        ['dentist_id' => $this->dentist->id],
        absolute: false,
    );

    $this->get($signed)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/statement-print')
            ->missing('dentists')
        );
});

test('guests cannot access the statement page', function () {
    auth()->logout();

    $this->get(route('ledger.statement'))->assertRedirect(route('login'));
});

test('guests cannot download the statement pdf', function () {
    auth()->logout();

    $this->get(route('ledger.statement.pdf'))->assertRedirect(route('login'));
});
