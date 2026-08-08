<?php

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;

/**
 * Balance for an account without dentist attribution. Used for aggregate
 * testing where attribution does not apply (e.g., cash).
 */
function balanceOf(string $code): int
{
    return (int) JournalLine::query()
        ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
        ->where('accounts.code', $code)
        ->selectRaw('COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0) as balance')
        ->value('balance');
}

test('a payment debits cash and credits the dentist receivable', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 300000,
        'payment_date' => '2026-06-15',
    ]);

    expect(balanceOf('1000'))->toBe(300000);   // cash box
    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(200000);   // still owed
});

test('a payment with no payment_date falls back to created_at', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);

    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 100000,
    ]);

    expect(JournalEntry::sole()->entry_date->toDateString())
        ->toBe($payment->created_at->toDateString());
});

test('deleting a payment restores the receivable', function () {
    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-10',
        'amount' => 500000,
        'status' => 'pending',
    ]);
    $payment = DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 300000,
        'payment_date' => '2026-06-15',
    ]);

    $payment->delete();

    expect(balanceOf('1000'))->toBe(0);
    expect(app(LedgerReports::class)->receivablesByDentist()[$dentist->id] ?? 0)->toBe(500000);
});
