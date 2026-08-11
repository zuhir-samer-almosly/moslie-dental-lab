<?php

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\JournalEntry;
use App\Models\Order;

test('creating an order debits receivables and credits revenue', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);

    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $entry = JournalEntry::sole();
    expect($entry->entry_date->toDateString())->toBe('2026-06-10');
    expect($entry->source_type)->toBe(Order::class);
    expect($entry->source_id)->toBe($order->id);
    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(500000);
    expect(app(LedgerReports::class)->balance(AccountCode::REVENUE->value))->toBe(500000);
});

test('editing an order rewrites its entry instead of adding one', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $order->update(['amount' => 400000]);

    expect(JournalEntry::count())->toBe(1);
    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(400000);
    expect(app(LedgerReports::class)->balance(AccountCode::REVENUE->value))->toBe(400000);
});

test('cancelling an order removes its entry', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $order->update(['status' => 'cancelled']);

    expect(JournalEntry::count())->toBe(0);
    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(0);
    expect(app(LedgerReports::class)->balance(AccountCode::REVENUE->value))->toBe(0);
});

test('un-cancelling an order posts it again', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'cancelled',
    ]);

    expect(JournalEntry::count())->toBe(0);

    $order->update(['status' => 'pending']);

    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(500000);
    expect(app(LedgerReports::class)->balance(AccountCode::REVENUE->value))->toBe(500000);
});

test('deleting an order removes its entry', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $order->delete();

    expect(JournalEntry::count())->toBe(0);
    expect(app(LedgerReports::class)->balance(AccountCode::REVENUE->value))->toBe(0);
});

test('a zero-amount order posts nothing', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 0,
        'status' => 'pending',
    ]);

    expect(JournalEntry::count())->toBe(0);
});

test('an order is posted on each of its item dates, not all on its due date', function () {
    $dentist = Dentist::create(['name' => 'د. ممتد']);

    // due_date is the earliest item date (2026-07-25). Posting the whole
    // 1,000 there would book August's work into July.
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-07-25',
        'amount' => 1000,
        'status' => 'pending',
    ]);
    $order->items()->create(['type' => 'قديم', 'quantity' => 1, 'price' => 700, 'meta' => ['date' => '2026-07-25']]);
    $order->items()->create(['type' => 'جديد', 'quantity' => 1, 'price' => 300, 'meta' => ['date' => '2026-08-02']]);

    $reports = app(LedgerReports::class);

    expect(JournalEntry::orderBy('entry_date')->pluck('entry_date')->map->toDateString()->all())
        ->toBe(['2026-07-25', '2026-08-02']);

    // Only July's work has been earned by the end of July.
    expect($reports->receivablesByDentist('2026-07-31')[$dentist->id] ?? 0)->toBe(700);
    // The order's total is unchanged — the split moves when, never how much.
    expect($reports->receivablesByDentist()[$dentist->id] ?? 0)->toBe(1000);
    expect($reports->revenue('2026-07-01', '2026-07-31'))->toBe(700);
    expect($reports->revenue('2026-08-01', '2026-08-31'))->toBe(300);
});

test('replacing an order items re-dates its entries', function () {
    $dentist = Dentist::create(['name' => 'د. معدّل']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-07-25',
        'amount' => 700,
        'status' => 'pending',
    ]);
    $order->items()->create(['type' => 'قديم', 'quantity' => 1, 'price' => 700, 'meta' => ['date' => '2026-07-25']]);

    // The controller's update path: the order row first, then its items
    // wholesale. The ledger has to end up describing the new items.
    $order->update(['amount' => 300, 'due_date' => '2026-08-02']);
    $order->items()->delete();
    $order->items()->create(['type' => 'جديد', 'quantity' => 1, 'price' => 300, 'meta' => ['date' => '2026-08-02']]);

    expect(JournalEntry::count())->toBe(1);
    expect(JournalEntry::sole()->entry_date->toDateString())->toBe('2026-08-02');
    expect(app(LedgerReports::class)->receivablesByDentist('2026-07-31')[$dentist->id] ?? 0)->toBe(0);
    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(300);
});

test('an item carrying no date of its own is posted on the order due date', function () {
    $dentist = Dentist::create(['name' => 'د. بلا تاريخ']);
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500,
        'status' => 'pending',
    ]);
    $order->items()->create(['type' => 'بلا تاريخ', 'quantity' => 1, 'price' => 500, 'meta' => ['date' => null]]);

    expect(JournalEntry::sole()->entry_date->toDateString())->toBe('2026-06-10');
    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(500);
});

test('an order whose amount disagrees with its items keeps its amount, with the difference on its due date', function () {
    $dentist = Dentist::create(['name' => 'د. متضارب']);

    // Drift the controller cannot produce, but a hand-edited row could: the
    // order is worth 1,000 while its items add up to 900. The order's total
    // must survive a rebuild intact or every historical figure moves.
    $order = Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-07-25',
        'amount' => 1000,
        'status' => 'pending',
    ]);
    $order->items()->create(['type' => 'قديم', 'quantity' => 1, 'price' => 600, 'meta' => ['date' => '2026-07-25']]);
    $order->items()->create(['type' => 'جديد', 'quantity' => 1, 'price' => 300, 'meta' => ['date' => '2026-08-02']]);

    $reports = app(LedgerReports::class);

    expect($reports->receivablesByDentist()[$dentist->id] ?? 0)->toBe(1000);
    expect($reports->receivablesByDentist('2026-07-31')[$dentist->id] ?? 0)->toBe(700);
});
