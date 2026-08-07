<?php

use App\Ledger\Ledger;
use App\Ledger\Line;
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
