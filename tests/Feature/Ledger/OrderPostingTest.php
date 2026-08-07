<?php

use App\Models\Dentist;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;

/** Receivable balance for a dentist, read straight from the lines. */
function receivable(int $dentistId): int
{
    return (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', '1100')
        ->where('journal_lines.dentist_id', $dentistId)
        ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
        ->value('balance');
}

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
    expect(receivable($dentist->id))->toBe(500000);
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
    expect(receivable($dentist->id))->toBe(400000);
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
    expect(receivable($dentist->id))->toBe(0);
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

    expect(receivable($dentist->id))->toBe(500000);
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
