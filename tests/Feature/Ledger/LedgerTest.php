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

    // Create a custom ledger that can be controlled for test phases
    $ledger = new class extends Ledger
    {
        public bool $shouldThrow = false;

        protected function postingFor(\Illuminate\Database\Eloquent\Model $source): ?\App\Ledger\Posting
        {
            return new class($this->shouldThrow) implements \App\Ledger\Posting
            {
                public function __construct(private bool $shouldThrow) {}

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
                    return 'test entry';
                }

                public function lines(): array
                {
                    if ($this->shouldThrow) {
                        // Return an unbalanced posting: 500 debits != 1000 credits
                        return [
                            \App\Ledger\Line::debit('1000', 500),
                            \App\Ledger\Line::credit('4000', 1000),
                        ];
                    }

                    // Return a balanced posting
                    return [
                        \App\Ledger\Line::debit('1000', 1000),
                        \App\Ledger\Line::credit('4000', 1000),
                    ];
                }
            };
        }
    };

    // Phase 1: Create an entry with source attached
    $ledger->shouldThrow = false;
    $ledger->sync($dentist);

    expect(JournalEntry::count())->toBe(1);
    $originalEntry = JournalEntry::first();
    expect($originalEntry->source_type)->toBe($dentist->getMorphClass());
    expect($originalEntry->source_id)->toBe($dentist->id);

    // Phase 2: Try to sync with unbalanced posting — should fail and rollback
    $ledger->shouldThrow = true;
    expect(fn () => $ledger->sync($dentist))->toThrow(UnbalancedEntryException::class);

    // Phase 3: Verify the original entry still exists with its id
    expect(JournalEntry::count())->toBe(1);
    $remainingEntry = JournalEntry::first();
    expect($remainingEntry->id)->toBe($originalEntry->id);
});
