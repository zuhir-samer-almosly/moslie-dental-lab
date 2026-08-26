<?php

use App\Models\Dentist;
use App\Models\ExchangeRate;
use App\Models\User;

test('the invoice carries the rate for reading its total in dollars', function () {
    // The owner reads a lira total back in dollars at the rate of the period's
    // last day — "we finished at 8/25, we take the price at 8/25".
    ExchangeRate::create(['rate_date' => '2026-08-25', 'rate' => '13']);
    $this->actingAs(User::factory()->create());
    Dentist::create(['name' => 'د. أحمد']);

    $this->get(route('invoices.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertInertia(fn ($page) => $page->where('closingRate', '13.000000'));
});

test('the invoice carries no rate when none was recorded by then', function () {
    ExchangeRate::create(['rate_date' => '2026-09-10', 'rate' => '14']);
    $this->actingAs(User::factory()->create());

    $this->get(route('invoices.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
        ->assertInertia(fn ($page) => $page->where('closingRate', null));
});

test('the dashboard carries today\'s rate', function () {
    ExchangeRate::create(['rate_date' => now()->toDateString(), 'rate' => '13']);
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('todayRate', '13.000000'));
});

test('the finance page carries the rate for its period end', function () {
    ExchangeRate::create(['rate_date' => '2026-08-01', 'rate' => '13']);
    $this->actingAs(User::factory()->create());

    $this->get(route('finance.index', ['month' => '2026-08']))
        ->assertInertia(fn ($page) => $page->where('closingRate', '13.000000'));
});
