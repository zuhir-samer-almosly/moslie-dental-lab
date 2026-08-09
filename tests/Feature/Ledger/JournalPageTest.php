<?php

use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Models\User;

test('the journal lists entries with both sides, newest first', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-06-10', 'amount' => 500000, 'status' => 'pending']);
    $payment = DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);

    $this->get(route('ledger.journal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ledger/journal')
            ->has('entries.data', 2)
            // orderByDesc('entry_date') then orderByDesc('id') — the payment
            // (06-15) must lead the order (06-10). A count assertion alone
            // can never catch the two being swapped.
            ->where('entries.data.0.entry_date', '2026-06-15')
            ->where('entries.data.0.description', "دفعة #{$payment->id}")
            ->has('entries.data.0.lines', 2)
            // Debit cash, credit the dentist's receivable — a journal that
            // rendered every debit as a credit still passes a bare count.
            ->where('entries.data.0.lines.0.account.code', '1000')
            ->where('entries.data.0.lines.0.debit', 200000)
            ->where('entries.data.0.lines.0.credit', 0)
            ->where('entries.data.0.lines.1.account.code', '1100')
            ->where('entries.data.0.lines.1.debit', 0)
            ->where('entries.data.0.lines.1.credit', 200000)
            ->where('entries.data.0.lines.1.dentist.id', $dentist->id)
            ->where('entries.data.1.entry_date', '2026-06-10')
            ->where('entries.data.1.description', "طلب #{$order->id}")
            ->has('entries.data.1.lines', 2)
            ->where('entries.data.1.lines.0.account.code', '1100')
            ->where('entries.data.1.lines.0.debit', 500000)
            ->where('entries.data.1.lines.0.credit', 0)
            ->where('entries.data.1.lines.0.dentist.id', $dentist->id)
            ->where('entries.data.1.lines.1.account.code', '4000')
            ->where('entries.data.1.lines.1.debit', 0)
            ->where('entries.data.1.lines.1.credit', 500000)
        );
});

test('the journal filters by account and date, keeping the whole entry', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);
    $order = Order::create(['dentist_id' => $dentist->id, 'due_date' => '2026-05-10', 'amount' => 100000, 'status' => 'pending']);
    $payment = DentistPayment::create(['dentist_id' => $dentist->id, 'amount' => 200000, 'payment_date' => '2026-06-15']);

    // Only the cash account moved in June — the payment entry survives, WITH
    // both of its lines: the cash line `whereHas` matched, and the
    // receivable line it never touched directly. If the query (or the page)
    // silently kept only the matching line, this would still be the entry
    // that survives — only the line count would give it away.
    $this->get(route('ledger.journal', ['account' => '1000', 'from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.description', "دفعة #{$payment->id}")
            ->has('entries.data.0.lines', 2)
            ->where('entries.data.0.lines.0.account.code', '1000')
            ->where('entries.data.0.lines.1.account.code', '1100')
        );

    // Date filter alone (May), no account — only the order entry is in range.
    $this->get(route('ledger.journal', ['from' => '2026-05-01', 'to' => '2026-05-31']))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.description', "طلب #{$order->id}")
        );
});

test('the journal paginates at 50 per page and keeps the active filter in its links', function () {
    $this->actingAs(User::factory()->create());

    $dentist = Dentist::create(['name' => 'د. سامي']);

    // 51 cash-touching entries — one page's worth plus one.
    foreach (range(1, 51) as $i) {
        DentistPayment::create([
            'dentist_id' => $dentist->id,
            'amount' => 1000,
            'payment_date' => sprintf('2026-01-%02d', ($i % 28) + 1),
        ]);
    }

    $this->get(route('ledger.journal', ['account' => '1000']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 50)
            ->where('entries.total', 51)
            ->where('entries.last_page', 2)
            // withQueryString() is what keeps `account` alive on the next
            // page's link — without it the link would silently drop the
            // filter and following it would widen the results.
            ->where(
                'entries.next_page_url',
                fn ($url) => str_contains((string) $url, 'account=1000') && str_contains((string) $url, 'page=2'),
            )
        );

    // Following that link (page 2, filter still applied) leaves the 51st.
    $this->get(route('ledger.journal', ['account' => '1000', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));
});

test('guests cannot reach the journal', function () {
    $this->get(route('ledger.journal'))->assertRedirect(route('login'));
});
