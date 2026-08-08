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

test('cancelled orders are excluded from the outstanding balance', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. ملغى']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-01', 'amount' => 40000, 'status' => 'pending']);
    // This one is cancelled — it must not count toward what's owed.
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-02', 'amount' => 100000, 'status' => 'cancelled']);

    $this->get(route('outstanding.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('totalOutstanding', 40000)
                ->where('dentists.0.outstanding', 40000)
        );
});

test('guests cannot access the outstanding page', function () {
    $this->get(route('outstanding.index'))->assertRedirect(route('login'));
});

test('outstanding balances come from the ledger and match the old formula', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. مطابقة']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-01', 'amount' => 700000, 'status' => 'pending']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-02', 'amount' => 300000, 'status' => 'recieved']);
    Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-03', 'amount' => 999999, 'status' => 'cancelled']);
    DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 250000, 'payment_date' => '2026-06-10']);

    $oldFormula = (int) Order::billable()->where('dentist_id', $dentist->id)->sum('amount')
        - (int) DentistPayment::where('dentist_id', $dentist->id)->sum('amount');

    // Prove the number is read from the ledger, not recomputed: wipe the
    // ledger and the page must report zero.
    $this->get(route('outstanding.index'))
        ->assertInertia(fn ($page) => $page->where('dentists.0.outstanding', $oldFormula));

    \App\Models\JournalEntry::query()->delete();

    $this->get(route('outstanding.index'))
        ->assertInertia(fn ($page) => $page->where('dentists.0.outstanding', 0));
});
