<?php

// tests/Feature/Money/CurrencyIsolationTest.php

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\User;

/**
 * A dollar dentist must be invisible to every lira figure in the system.
 *
 * The same lira activity is run twice — once alone, once alongside a busy
 * dollar dentist — and every lira report must return the identical number.
 * This is the property that makes parallel accounts worth their extra rows:
 * not that the dollar side works, but that the lira side cannot notice it.
 */
function liraActivity(): void
{
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    test()->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [
            ['type' => 'جسر', 'quantity' => 2, 'date' => '2026-09-03',
                'price' => 400, 'selected_teeth' => []],
            ['type' => 'تلبيسة', 'quantity' => 1, 'date' => '2026-09-28',
                'price' => 150, 'selected_teeth' => []],
        ],
    ])->assertSessionHasNoErrors();

    test()->post(route('payments.store'), [
        'dentist_id' => $dentist->id, 'payment_date' => '2026-09-15', 'amount' => 300,
    ])->assertSessionHasNoErrors();
}

function dollarActivity(): void
{
    $dentist = Dentist::create(['name' => 'د. سامي', 'currency' => 'USD']);

    test()->post(route('orders.store'), [
        'dentist_id' => $dentist->id, 'status' => 'pending',
        'items' => [['type' => 'زيركون', 'quantity' => 3, 'date' => '2026-09-05',
            'currency' => 'USD', 'original_amount' => 250_00, 'selected_teeth' => []]],
    ])->assertSessionHasNoErrors();

    test()->post(route('payments.store'), [
        'dentist_id' => $dentist->id, 'payment_date' => '2026-09-18',
        'currency' => 'USD', 'original_amount' => '400',
    ])->assertSessionHasNoErrors();
}

/** @return array<string, int> every lira figure the app reports */
function liraFigures(): array
{
    $reports = app(LedgerReports::class);

    return [
        'cash' => $reports->balance('1000'),
        'receivable' => $reports->balance('1100'),
        'revenue' => $reports->balance('4000'),
        'monthly_receipts' => $reports->cashReceipts('2026-09-01', '2026-09-30'),
        'monthly_revenue' => $reports->revenue('2026-09-01', '2026-09-30'),
        'receivables_total' => (int) $reports->receivablesByDentist()->sum(),
    ];
}

test('a dollar dentist changes no lira figure anywhere', function () {
    $this->actingAs(User::factory()->create());

    liraActivity();
    $alone = liraFigures();

    dollarActivity();
    $alongside = liraFigures();

    expect($alongside)->toBe($alone)
        // ...and the lira figures are non-trivial, or the assertion above
        // would pass on a pair of empty reports.
        ->and($alone['revenue'])->toBe(950)
        ->and($alone['cash'])->toBe(300)
        ->and($alone['receivable'])->toBe(650)
        ->and($alone['monthly_receipts'])->toBe(300)
        ->and($alone['monthly_revenue'])->toBe(950)
        ->and($alone['receivables_total'])->toBe(650);
});

test('the dollar dentist is meanwhile visible in the dollar accounts', function () {
    $this->actingAs(User::factory()->create());

    dollarActivity();

    $reports = app(LedgerReports::class);

    expect($reports->balance('4001'))->toBe(75000)  // 3 x $250
        ->and($reports->balance('1001'))->toBe(40000)
        ->and($reports->balance('1101'))->toBe(35000);
});
