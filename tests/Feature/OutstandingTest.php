<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;

test('the outstanding page lists each dentist net balance, highest first', function () {
    $this->actingAs(User::factory()->create());

    $behind = Dentist::create(['name' => 'د. متأخر']);
    Order::create(['dentist_id' => $behind->id, 'due_date' => '2026-06-01', 'amount' => 100000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $behind->id, 'amount' => 30000, 'payment_date' => '2026-06-10']);

    $settled = Dentist::create(['name' => 'د. منتظم']);
    Order::create(['dentist_id' => $settled->id, 'due_date' => '2026-06-01', 'amount' => 50000, 'status' => 'pending']);
    DentistPayment::create(['dentist_id' => $settled->id, 'amount' => 50000, 'payment_date' => '2026-06-10']);

    $this->get(route('outstanding.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('outstanding/index')
                ->where('totalOutstanding', 70000)
                ->where('dentists.0.name', 'د. متأخر')
                ->where('dentists.0.outstanding', 70000)
                ->where('dentists.1.outstanding', 0)
        );
});

test('guests cannot access the outstanding page', function () {
    $this->get(route('outstanding.index'))->assertRedirect(route('login'));
});
