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

    $this->from(route('dentists.index'))
        ->delete(route('dentists.destroy', $dentist))
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

    $this->from(route('dentists.index'))
        ->delete(route('dentists.destroy', $dentist))
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

    $this->from(route('dentists.index'))
        ->delete(route('dentists.destroy', $dentist))
        ->assertRedirect(route('dentists.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('dentists', ['id' => $dentist->id]);
    $this->assertDatabaseHas('dentist_payments', ['dentist_id' => $dentist->id]);
});

test('storing a dentist from the order page redirects back and saves the price list', function () {
    $this->actingAs(User::factory()->create());

    $this->from(route('orders.create'))
        ->post(route('dentists.store'), [
            'name' => 'د. خالد',
            'gender' => 'male',
            'price_list' => [
                'خزف' => ['price' => 90000, 'currency' => 'SYP'],
                'زيركون' => ['price' => 150000, 'currency' => 'SYP'],
            ],
        ])
        ->assertRedirect(route('orders.create'))
        ->assertSessionHas('success');

    expect(Dentist::firstWhere('name', 'د. خالد')->price_list)->toBe([
        'خزف' => ['price' => 90000, 'currency' => 'SYP'],
        'زيركون' => ['price' => 150000, 'currency' => 'SYP'],
    ]);
});

test('storing a dentist from the standalone page redirects to the index', function () {
    $this->actingAs(User::factory()->create());

    $this->from(route('dentists.create'))
        ->post(route('dentists.store'), [
            'name' => 'د. ليلى',
            'gender' => 'female',
            'to_index' => true,
        ])
        ->assertRedirect(route('dentists.index'))
        ->assertSessionHas('success');

    $dentist = Dentist::firstWhere('name', 'د. ليلى');

    expect($dentist)->not->toBeNull()
        ->and($dentist->getAttributes())->not->toHaveKey('to_index');
});

test('updating one work type price leaves the rest of the price list intact', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create([
        'name' => 'د. سامر',
        'price_list' => ['خزف' => 90000, 'زيركون' => 150000],
    ]);

    $this->from(route('orders.create'))
        ->put(route('dentists.update', $dentist), [
            'name' => 'د. سامر',
            'gender' => 'male',
            'price_list' => [
                'خزف' => ['price' => 110000, 'currency' => 'SYP'],
                'زيركون' => ['price' => 150000, 'currency' => 'SYP'],
            ],
        ])
        ->assertRedirect(route('orders.create'))
        ->assertSessionHas('success');

    expect($dentist->fresh()->price_list)->toBe([
        'خزف' => ['price' => 110000, 'currency' => 'SYP'],
        'زيركون' => ['price' => 150000, 'currency' => 'SYP'],
    ]);
});

test('the standalone dentist pages still load', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);

    $this->get(route('dentists.create'))->assertOk();
    $this->get(route('dentists.edit', $dentist))->assertOk();
});
