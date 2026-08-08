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
