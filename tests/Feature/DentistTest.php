<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;

test('guests cannot access dentists', function () {
    $this->get(route('dentists.index'))->assertRedirect(route('login'));
});

test('a dentist without financial history can be deleted', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. مؤقت']);

    $this->delete(route('dentists.destroy', $dentist))
        ->assertRedirect(route('dentists.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('dentists', ['id' => $dentist->id]);
});

test('a dentist with orders cannot be deleted', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);
    Order::create([
        'dentist_id' => $dentist->id,
        'due_date' => '2026-06-15',
        'amount' => 1000,
        'status' => 'pending',
    ]);

    $this->delete(route('dentists.destroy', $dentist))
        ->assertRedirect(route('dentists.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('dentists', ['id' => $dentist->id]);
    $this->assertDatabaseHas('orders', ['dentist_id' => $dentist->id]);
});

test('a dentist with payments cannot be deleted', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);
    DentistPayment::create([
        'dentist_id' => $dentist->id,
        'amount' => 5000,
        'payment_date' => '2026-06-15',
    ]);

    $this->delete(route('dentists.destroy', $dentist))
        ->assertRedirect(route('dentists.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('dentists', ['id' => $dentist->id]);
    $this->assertDatabaseHas('dentist_payments', ['dentist_id' => $dentist->id]);
});
