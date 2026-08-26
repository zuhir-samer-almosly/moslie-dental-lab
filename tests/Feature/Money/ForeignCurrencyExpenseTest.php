<?php

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\User;
use App\Money\Rate;

test('a material bought in dollars is booked as lira', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('material-purchases.store'), [
        'name' => 'زيركون',
        'quantity' => '1 كغ',
        'purchase_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '40',
        'rate' => '13',
    ])->assertRedirect(route('material-purchases.index'));

    expect(MaterialPurchase::sole())
        ->amount->toBe(520)
        ->currency->toBe('USD')
        ->original_amount->toBe(4000);

    expect(app(LedgerReports::class)->balance(AccountCode::MATERIALS->value))->toBe(520);
});

test('a general expense paid in dollars is booked as lira', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => 'rent',
        'description' => 'أجرة',
        'expense_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '100',
        'rate' => '13',
    ])->assertRedirect(route('expenses.index'));

    expect(Expense::sole())->amount->toBe(1300)->currency->toBe('USD');
});

test('a salary paid in dollars is booked as lira', function () {
    $this->actingAs(User::factory()->create());
    $employee = Employee::create(['name' => 'موظف', 'is_active' => true]);

    $this->post(route('employee-payments.store'), [
        'employee_id' => $employee->id,
        'payment_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '50',
        'rate' => '13',
    ])->assertRedirect();

    expect(\App\Models\EmployeePayment::sole())->amount->toBe(650);
    expect(app(LedgerReports::class)->balance(AccountCode::SALARIES->value))->toBe(650);
});

test('a dollar expense remembers its rate for the day', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => 'rent',
        'description' => 'أجرة',
        'expense_date' => '2026-08-02',
        'currency' => 'USD',
        'original_amount' => '100',
        'rate' => '13',
    ]);

    expect(Rate::on('2026-08-02'))->toBe('13.000000');
});

test('lira expenses still submit with no currency field', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => 'rent',
        'description' => 'أجرة',
        'expense_date' => '2026-08-02',
        'amount' => 5000,
    ])->assertRedirect(route('expenses.index'));

    expect(Expense::sole())->amount->toBe(5000)->currency->toBe('SYP')->rate->toBeNull();
});
