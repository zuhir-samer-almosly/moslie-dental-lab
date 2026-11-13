<?php

use App\Models\Dentist;
use App\Models\User;

test('an entry login reaches the daybook and the notebooks', function () {
    $this->actingAs(User::factory()->entry()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);

    $this->get(route('daybook.index'))->assertOk();
    $this->get(route('notebook.index'))->assertOk();
    $this->get(route('notebook.show', $dentist))->assertOk();
});

test('an entry login is kept out of the rest of the app', function (string $url) {
    $this->actingAs(User::factory()->entry()->create());

    $this->get($url)->assertForbidden();
})->with([
    '/orders', '/orders/create', '/payments', '/dentists', '/invoices', '/outstanding',
    '/employees', '/material-purchases', '/expenses', '/finance', '/report',
    '/ledger/cash', '/ledger/journal', '/ledger/statement', '/work-types', '/search?q=a',
]);

test('an entry login cannot write through the admin routes', function () {
    $this->actingAs(User::factory()->entry()->create());

    $this->post(route('orders.store'), [])->assertForbidden();
    $this->post(route('payments.store'), [])->assertForbidden();
    $this->post(route('dentists.store'), ['name' => 'x'])->assertForbidden();
    $this->post(route('exchange-rate.store'), ['rate' => 1])->assertForbidden();
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
});

test('an entry login lands on the daybook instead of the dashboard', function () {
    $this->actingAs(User::factory()->entry()->create());

    $this->get('/')->assertRedirect(route('daybook.index'));
    $this->get(route('dashboard'))->assertRedirect(route('daybook.index'));
});

test('an entry login is not sent the expense categories', function () {
    $this->actingAs(User::factory()->entry()->create());

    $this->get(route('daybook.index'))
        ->assertInertia(fn ($page) => $page
            ->where('expenseCategories', [])
            ->where('auth.user.role', 'entry'));
});

test('an admin keeps the whole app, the daybook included', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('orders.index'))->assertOk();
    $this->get(route('daybook.index'))->assertOk();
});

test('the create-user command can make an entry login', function () {
    $this->artisan('app:create-user', [
        '--name' => 'الوالد', '--email' => 'lab@example.com', '--password' => 'secret-pass', '--role' => 'entry',
    ])->assertSuccessful();

    expect(User::sole()->role)->toBe('entry');

    $this->artisan('app:create-user', [
        '--name' => 'x', '--email' => 'x@example.com', '--password' => 'secret-pass', '--role' => 'boss',
    ])->assertFailed();
});
