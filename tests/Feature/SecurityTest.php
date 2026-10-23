<?php

use App\Models\Account;
use App\Models\User;
use App\Money\Rate;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| Guest exposure of Inertia shared props
|--------------------------------------------------------------------------
|
| Every Inertia response embeds the shared props as JSON in the page's
| `data-page` attribute — the login page included. Anything shared
| unconditionally in HandleInertiaRequests is therefore readable by anyone who
| can reach /login and view source, with no account and no session.
|
| The day's lira-per-dollar rate and the chart of accounts' expense categories
| were both leaking that way. These tests pin them shut.
|
*/

test('the login page does not leak the exchange rate to guests', function () {
    Rate::set(now()->toDateString(), '12345');

    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('dailyRate.rate', null)
        ->where('dailyRate.recorded_today', false)
    );

    // Belt and braces: the rate must not appear anywhere in the raw HTML,
    // whatever shape a future prop takes.
    expect($response->getContent())->not->toContain('12345');
});

test('the login page does not leak expense categories to guests', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('expenseCategories', [])
        ->where('expenseCategoryLabels', [])
    );

    $anyCategory = Account::allExpenseCategories()->first();

    expect($anyCategory)->not->toBeNull()
        ->and($response->getContent())->not->toContain($anyCategory->name);
});

test('authenticated users still receive the rate and the expense categories', function () {
    Rate::set(now()->toDateString(), '12345');

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('dailyRate.recorded_today', true)
        ->has('expenseCategories.other')
        ->has('expenseCategoryLabels.other')
    );
});

/*
|--------------------------------------------------------------------------
| Rate limiting on unauthenticated auth endpoints
|--------------------------------------------------------------------------
|
| Fortify throttles login and the two-factor challenge but registers the
| password-reset POSTs behind `guest` alone. Unlimited, unauthenticated
| requests there are a free mail/log flood and an unbounded guessing window
| against a reset token.
|
*/

test('requesting a password reset link is rate limited', function () {
    RateLimiter::clear('');

    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertStatus(429);
});

test('submitting a new password is rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $response = $this->post(route('password.update'), [
            'token' => 'guessed-token-'.$attempt,
            'email' => 'someone@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        expect($response->getStatusCode())->not->toBe(429);
    }

    $this->post(route('password.update'), [
        'token' => 'guessed-token-6',
        'email' => 'someone@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertStatus(429);
});

test('the throttle does not apply to ordinary fortify routes', function () {
    // Limit::none() for everything that isn't a password-reset POST — logging
    // out twenty times in a minute must not lock anyone out.
    $user = User::factory()->create();

    foreach (range(1, 20) as $attempt) {
        $this->actingAs($user)->post(route('logout'))->assertStatus(302);
    }
});

/*
|--------------------------------------------------------------------------
| Public self-registration
|--------------------------------------------------------------------------
|
| Single shared dataset with no per-user scoping: an account created by a
| stranger would see every dentist, order and payment in the business.
|
*/

test('public registration is not reachable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'intruder@example.com')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Content-Security-Policy escape hatch
|--------------------------------------------------------------------------
|
| CSP_ENABLED=false must switch the policy off in production, where
| `artisan optimize` caches the config. Under a cached config env() returns
| null outside config files, so the switch has to be read through config().
|
*/

test('the content security policy is sent by default', function () {
    $this->get('/login')->assertHeader('Content-Security-Policy');
});

test('the content security policy can be switched off through config', function () {
    config(['app.csp_enabled' => false]);

    $this->get('/login')->assertHeaderMissing('Content-Security-Policy');
});
