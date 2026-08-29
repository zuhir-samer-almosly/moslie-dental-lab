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
use Illuminate\Support\Carbon;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows this month income, expenses and net', function () {
    $this->actingAs(User::factory()->create());
    $today = Carbon::now()->startOfMonth()->addDays(2)->toDateString();

    $dentist = Dentist::create(['name' => 'د. سامر']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 60000, 'payment_date' => $today]);

    EmployeePayment::factory()->create([
        'employee_id' => Employee::factory()->create()->id,
        'amount' => 30000,
        'payment_date' => $today,
    ]);
    MaterialPurchase::factory()->create(['amount' => 5000, 'purchase_date' => $today]);
    Expense::factory()->create(['category' => 'transport', 'amount' => 2000, 'expense_date' => $today]);

    $this->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('dashboard')
                ->where('stats.income', 60000)
                ->where('stats.expenses', 37000) // 30000 + 5000 + 2000
                ->where('stats.net', 23000)
                // Pairwise-distinct amounts: a swapped SALARIES/MATERIALS
                // code, or a `general_expenses` reject list missing a code,
                // would show up here even though the totals above still add
                // up.
                ->where('stats.salaries', 30000)
                ->where('stats.materials', 5000)
                ->where('stats.general_expenses', 2000)
        );
});

test('the dashboard reports the cash box balance from the ledger, not recomputed from the domain tables', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => now()->toDateString(),
        'amount' => 500000,
        'status' => 'pending',
    ]);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 200000,
        'payment_date' => now()->toDateString(),
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.cash_balance', 200000)
            ->where('stats.outstanding', 300000)
            ->where('stats.earned', 500000)
        );

    // Prove the figures are read from the ledger, not recomputed: wipe the
    // ledger while the domain rows survive, and the page must report zero.
    JournalEntry::query()->delete();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.income', 0)
            ->where('stats.earned', 0)
            ->where('stats.cash_balance', 0)
            ->where('stats.outstanding', 0)
            ->where('stats.net', 0)
        );

    expect(Order::count())->toBe(1);
    expect(DentistPayment::count())->toBe(1);
});

test('the dashboard shows a dollar dentist alongside a lira dentist in separate columns, never summed', function () {
    $this->actingAs(User::factory()->create());
    $today = Carbon::now()->startOfMonth()->addDays(2)->toDateString();

    $lira = Dentist::create(['name' => 'د. ليرة']);
    Order::create(['dentist_id' => $lira->id, 'due_date' => $today, 'amount' => 500000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $lira->id, 'amount' => 200000, 'payment_date' => $today]);

    $dollar = Dentist::create(['name' => 'د. دولار', 'currency' => 'USD']);
    Order::create(['dentist_id' => $dollar->id, 'due_date' => $today, 'amount' => 0, 'original_amount' => 50000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $dollar->id, 'original_amount' => 20000, 'payment_date' => $today]);

    // Both currencies are non-zero at once: a regression that summed the
    // lira and dollar figures anywhere in DashboardController — income,
    // earned, outstanding, or cash_balance — would show up here, whereas a
    // test with only one currency populated could pass a blended sum by
    // accident.
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.income', 200000)
            ->where('stats.income_usd', 20000)
            ->where('stats.earned', 500000)
            ->where('stats.earned_usd', 50000)
            ->where('stats.outstanding', 300000)
            ->where('stats.outstanding_usd', 30000)
            ->where('stats.cash_balance', 200000)
            ->where('stats.cash_balance_usd', 20000)
        );
});
