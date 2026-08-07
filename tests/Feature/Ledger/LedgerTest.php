<?php

use App\Ledger\Ledger;
use App\Ledger\Line;
use App\Ledger\Posting;
use App\Ledger\UnbalancedEntryException;
use App\Models\JournalEntry;
use App\Models\JournalLine;

test('a balanced entry is persisted with its lines', function () {
    $entry = app(Ledger::class)->post('2026-06-01', 'اختبار', [
        Line::debit('1000', 5000),
        Line::credit('4000', 5000),
    ]);

    expect(JournalEntry::count())->toBe(1);
    expect($entry->entry_date->toDateString())->toBe('2026-06-01');
    expect($entry->description)->toBe('اختبار');

    $lines = JournalLine::orderBy('id')->get();
    expect($lines)->toHaveCount(2);
    expect($lines[0]->debit)->toBe(5000);
    expect($lines[0]->credit)->toBe(0);
    expect($lines[1]->credit)->toBe(5000);
});

test('an unbalanced entry is refused and nothing is written', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'مختل', [
        Line::debit('1000', 5000),
        Line::credit('4000', 4000),
    ]))->toThrow(UnbalancedEntryException::class);

    expect(JournalEntry::count())->toBe(0);
    expect(JournalLine::count())->toBe(0);
});

test('an entry with no lines is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'فارغ', []))
        ->toThrow(UnbalancedEntryException::class);
});

test('a line carrying both a debit and a credit is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'خطأ', [
        new Line('1000', 500, 500, null),
    ]))->toThrow(UnbalancedEntryException::class);
});

test('a dentist id is stored on the line it belongs to', function () {
    $dentist = \App\Models\Dentist::create(['name' => 'د. تجربة']);

    app(Ledger::class)->post('2026-06-01', 'اختبار', [
        Line::debit('1100', 700, $dentist->id),
        Line::credit('4000', 700),
    ]);

    $lines = JournalLine::orderBy('id')->get();
    expect($lines[0]->dentist_id)->toBe($dentist->id);
    expect($lines[1]->dentist_id)->toBeNull();
});

test('a line with negative debit is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'سالب', [
        Line::debit('1000', -5000),
        Line::credit('4000', 5000),
    ]))->toThrow(UnbalancedEntryException::class);

    expect(JournalEntry::count())->toBe(0);
});

test('a line with negative credit is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'سالب', [
        Line::debit('1000', 5000),
        Line::credit('4000', -5000),
    ]))->toThrow(UnbalancedEntryException::class);

    expect(JournalEntry::count())->toBe(0);
});

test('a line carrying neither debit nor credit is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'فارغ', [
        new Line('1000', 0, 0, null),
        Line::credit('4000', 0),
    ]))->toThrow(UnbalancedEntryException::class);

    expect(JournalEntry::count())->toBe(0);
});

test('an entry with zero total debits and credits is refused', function () {
    expect(fn () => app(Ledger::class)->post('2026-06-01', 'صفر', [
        new Line('1000', 0, 0, null),
    ]))->toThrow(UnbalancedEntryException::class);

    expect(JournalEntry::count())->toBe(0);
});

test('sync rolls back forget on posting failure', function () {
    $dentist = \App\Models\Dentist::create(['name' => 'د. تجربة']);

    // Create a ledger entry for the dentist first
    app(Ledger::class)->post('2026-06-01', 'أول', [
        Line::debit('1100', 1000),
        Line::credit('4000', 1000),
    ]);

    expect(JournalEntry::count())->toBe(1);
    $originalEntry = JournalEntry::first();

    // Create a custom ledger with a test-only posting that will throw
    $ledger = new class extends Ledger
    {
        protected function postingFor(\Illuminate\Database\Eloquent\Model $source): ?\App\Ledger\Posting
        {
            // Return a deliberately unbalanced posting
            return new class implements \App\Ledger\Posting
            {
                public function shouldPost(): bool
                {
                    return true;
                }

                public function date(): string
                {
                    return '2026-06-02';
                }

                public function description(): string
                {
                    return 'unbalanced';
                }

                public function lines(): array
                {
                    return [
                        \App\Ledger\Line::debit('1000', 500),
                        \App\Ledger\Line::credit('4000', 1000), // This is unbalanced: 500 != 1000
                    ];
                }
            };
        }
    };

    // Try to sync with the unbalanced posting
    expect(fn () => $ledger->sync($dentist))->toThrow(UnbalancedEntryException::class);

    // The original entry should still exist (forget/rollback occurred)
    expect(JournalEntry::count())->toBe(1);
    $remainingEntry = JournalEntry::first();
    expect($remainingEntry->id)->toBe($originalEntry->id);
});
