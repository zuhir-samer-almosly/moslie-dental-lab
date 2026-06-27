<?php

use App\Models\MaterialPurchase;
use App\Models\User;

test('guests cannot access materials', function () {
    $this->get(route('material-purchases.index'))->assertRedirect(route('login'));
});

test('a material purchase can be recorded', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('material-purchases.store'), [
        'name' => 'خزف',
        'supplier' => 'شركة المواد',
        'quantity' => '5 كغ',
        'amount' => 100,
        'purchase_date' => '2026-06-10',
    ])->assertRedirect(route('material-purchases.index'));

    $this->assertDatabaseHas('material_purchases', [
        'name' => 'خزف',
        'amount' => 100,
    ]);
});

test('the materials index filters by month', function () {
    $this->actingAs(User::factory()->create());

    MaterialPurchase::factory()->create([
        'amount' => 100,
        'purchase_date' => '2026-06-05',
    ]);
    MaterialPurchase::factory()->create([
        'amount' => 999,
        'purchase_date' => '2026-05-05',
    ]);

    $this->get(route('material-purchases.index', ['month' => '2026-06']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('material-purchases/index')
                ->where('total', 100)
                ->has('purchases', 1)
        );
});

test('a material name and amount are required', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('material-purchases.store'), [
        'name' => '',
        'amount' => '',
        'purchase_date' => '2026-06-10',
    ])->assertSessionHasErrors(['name', 'amount']);
});
