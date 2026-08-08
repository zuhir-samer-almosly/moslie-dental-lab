<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\MaterialPurchase;
use App\Models\Order;
use App\Models\User;

test('finance summary computes net = income - (salaries + materials + expenses) for the month', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامر']);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 60000,
        'payment_date' => '2026-06-15',
    ]);
    // Different month — must be excluded.
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 99999,
        'payment_date' => '2026-05-15',
    ]);

    $employee = Employee::factory()->create();
    EmployeePayment::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 30000,
        'payment_date' => '2026-06-20',
    ]);

    MaterialPurchase::factory()->create([
        'name' => 'خزف',
        'amount' => 5000,
        'purchase_date' => '2026-06-18',
    ]);
    // Different month — must be excluded.
    MaterialPurchase::factory()->create([
        'amount' => 7777,
        'purchase_date' => '2026-05-18',
    ]);

    Expense::factory()->create([
        'category' => 'transport',
        'amount' => 2000,
        'expense_date' => '2026-06-22',
    ]);
    // Different month — must be excluded.
    Expense::factory()->create([
        'amount' => 3333,
        'expense_date' => '2026-05-22',
    ]);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('finance/index')
                ->where('income', 60000)
                ->where('expenses', 37000)
                ->where('net', 23000)
                ->where('month', '2026-06')
                ->where('expensesByMaterial.0.name', 'خزف')
                ->where('expensesByMaterial.0.total', 5000)
                ->where('expensesByCategory.0.name', 'مواصلات وسفر')
                ->where('expensesByCategory.0.total', 2000)
                ->has('trend', 6)
        );
});

test('requested month is honored even on day 31 (no day-overflow into the next month)', function () {
    // Regression: Carbon::createFromFormat('Y-m', ...) fills the missing day
    // from "now", so parsing "2026-06" on July 31 overflowed to July 1 and
    // the report silently showed the wrong month's numbers.
    $this->travelTo('2026-07-31 10:00:00');
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامر']);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 40000,
        'payment_date' => '2026-06-10',
    ]);
    // July payment — must NOT leak into June's report.
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 11111,
        'payment_date' => '2026-07-10',
    ]);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('finance/index')
                ->where('month', '2026-06')
                ->where('income', 40000)
        );
});

test('guests cannot access the finance page', function () {
    $this->get(route('finance.index'))->assertRedirect(route('login'));
});

test('the finance page reports cash received, work earned and receivables', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    // 500,000 of work delivered in June, only 200,000 collected.
    \App\Models\Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('income', 200000)       // cash headline
            ->where('expenses', 40000)
            ->where('net', 160000)
            ->where('earned', 500000)       // work delivered
            ->where('receivables', 300000)  // still owed
        );
});

test('expense categories on the finance page come from accounts with movement', function () {
    $this->actingAs(User::factory()->create());

    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);
    MaterialPurchase::create(['name' => 'خزف', 'amount' => 25000, 'purchase_date' => '2026-06-02']);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertInertia(function ($page) {
            $categories = collect($page->toArray()['props']['categories']);

            // Only accounts with actual movement appear — no empty buckets.
            expect($categories->pluck('total', 'label')->all())->toBe([
                'المواد' => 25000,
                'إيجار' => 40000,
            ]);
        });
});

test('cancelled orders do not count toward work earned or receivables', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. ملغى']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-05', 'amount' => 80000, 'status' => 'pending']);
    // Cancelled — must not post to the ledger at all, so it cannot inflate
    // earned work or the outstanding receivable.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-06', 'amount' => 500000, 'status' => 'cancelled']);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertInertia(fn ($page) => $page
            ->where('earned', 80000)
            ->where('receivables', 80000)
        );
});

test('a payment and an expense landing on the last day of the month are both included', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. حدّي']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-30', 'amount' => 100000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 100000, 'payment_date' => '2026-06-30']);
    Expense::create(['category' => 'rent', 'amount' => 15000, 'expense_date' => '2026-06-30']);
    // The first day of the next month — must NOT leak into June's report.
    Expense::create(['category' => 'rent', 'amount' => 99999, 'expense_date' => '2026-07-01']);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertInertia(fn ($page) => $page
            ->where('income', 100000)
            ->where('earned', 100000)
            ->where('expenses', 15000)
        );
});

test('expensesByCategory excludes salaries and materials and sorts general expenses by amount, not account order', function () {
    $this->actingAs(User::factory()->create());

    $employee = Employee::factory()->create();
    EmployeePayment::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 90000,
        'payment_date' => '2026-06-05',
    ]);
    MaterialPurchase::factory()->create([
        'amount' => 80000,
        'purchase_date' => '2026-06-06',
    ]);
    // Rent (5220) sorts after transport (5200) by account/sort_order, but
    // has the larger amount — if this table ever reverts to sort_order
    // instead of amount-descending, rent and transport swap places.
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);
    Expense::create(['category' => 'transport', 'amount' => 15000, 'expense_date' => '2026-06-02']);

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['expensesByCategory'])->all();

            // Strict: excludes salaries (90000) and materials (80000), and
            // is ordered biggest-first among what remains.
            expect($rows)->toBe([
                ['name' => 'إيجار', 'total' => 40000],
                ['name' => 'مواصلات وسفر', 'total' => 15000],
            ]);
        });
});

test('finance figures are read from the ledger, not recomputed from domain tables', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. مطابقة']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);
    Expense::create(['category' => 'rent', 'amount' => 40000, 'expense_date' => '2026-06-01']);

    // Prove the figures come from the ledger: wipe it and everything must
    // report zero, even though the domain rows (orders, payments, expenses)
    // are untouched.
    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertInertia(fn ($page) => $page
            ->where('income', 200000)
            ->where('earned', 500000)
            ->where('receivables', 300000)
            ->where('expenses', 40000)
        );

    JournalEntry::query()->delete();

    $this->get(route('finance.index', ['month' => '2026-06']))
        ->assertInertia(fn ($page) => $page
            ->where('income', 0)
            ->where('earned', 0)
            ->where('receivables', 0)
            ->where('expenses', 0)
            ->where('net', 0)
            ->where('categories', [])
        );
});
