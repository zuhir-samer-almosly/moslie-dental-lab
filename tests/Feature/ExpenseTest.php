<?php

use App\Models\Account;
use App\Models\Expense;
use App\Models\User;

test('guests cannot access expenses', function () {
    $this->get(route('expenses.index'))->assertRedirect(route('login'));
});

test('an expense can be recorded', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => 'transport',
        'description' => 'رحلة إلى دمشق',
        'amount' => 50000,
        'expense_date' => '2026-06-10',
    ])->assertRedirect(route('expenses.index'));

    $this->assertDatabaseHas('expenses', [
        'category' => 'transport',
        'description' => 'رحلة إلى دمشق',
        'amount' => 50000,
    ]);
});

test('the expenses index filters by month', function () {
    $this->actingAs(User::factory()->create());

    Expense::factory()->create([
        'amount' => 100,
        'expense_date' => '2026-06-05',
    ]);
    Expense::factory()->create([
        'amount' => 999,
        'expense_date' => '2026-05-05',
    ]);

    $this->get(route('expenses.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('expenses/index')
                ->where('total', 100)
                ->has('expenses', 1)
        );
});

test('the month filter is honored even on day 31 (no day-overflow into the next month)', function () {
    $this->travelTo('2026-07-31 10:00:00');
    $this->actingAs(User::factory()->create());

    Expense::factory()->create([
        'amount' => 100,
        'expense_date' => '2026-06-05',
    ]);
    Expense::factory()->create([
        'amount' => 999,
        'expense_date' => '2026-07-05',
    ]);

    $this->get(route('expenses.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('expenses/index')
                ->where('month', '2026-06')
                ->where('total', 100)
                ->has('expenses', 1)
        );
});

test('an expense category and amount are required', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => '',
        'amount' => '',
        'expense_date' => '2026-06-10',
    ])->assertSessionHasErrors(['category', 'amount']);
});

test('an expense category must be one of the allowed values', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => 'bogus',
        'amount' => 100,
        'expense_date' => '2026-06-10',
    ])->assertSessionHasErrors(['category']);
});

test('expense categories are shared from the accounts table', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $this->get(route('expenses.index'))
        ->assertInertia(fn ($page) => $page
            ->where('expenseCategories.rent', 'إيجار')
            ->where('expenseCategories.transport', 'مواصلات وسفر')
        );
});

test('an expense category must exist as an account', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $this->post(route('expenses.store'), [
        'category' => 'not_a_category',
        'amount' => 1000,
        'expense_date' => '2026-06-01',
    ])->assertSessionHasErrors('category');

    $this->post(route('expenses.store'), [
        'category' => 'rent',
        'amount' => 1000,
        'expense_date' => '2026-06-01',
    ])->assertSessionHasNoErrors();
});

test('a deactivated category is dropped from the shared prop but a pre-existing expense in it still validates on re-save', function () {
    $this->actingAs(User::factory()->create());

    Account::where('category_key', 'rent')->update(['is_active' => false]);
    Account::flushChart();

    // The UI-facing list must not offer the deactivated category...
    $this->get(route('expenses.index'))
        ->assertInertia(fn ($page) => $page
            ->where('expenseCategories.transport', 'مواصلات وسفر')
            ->missing('expenseCategories.rent')
        );

    // ...but an existing expense recorded under it must still be re-savable,
    // otherwise deactivating a category would lock every past expense in it.
    $expense = Expense::factory()->create(['category' => 'rent']);

    $this->put(route('expenses.update', $expense), [
        'category' => 'rent',
        'amount' => 2000,
        'expense_date' => '2026-06-01',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'category' => 'rent',
        'amount' => 2000,
    ]);
});
