<?php

use App\Ledger\AccountCode;
use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Ten items on ten distinct dates — one ledger entry per date is the target. */
function tenItems(): array
{
    $items = [];
    for ($i = 1; $i <= 10; $i++) {
        $items[] = [
            'type' => "نوع {$i}",
            'quantity' => 1,
            'price' => 100,
            'date' => sprintf('2026-08-%02d', $i),
            'selected_teeth' => [],
        ];
    }

    return $items;
}

function countEntryInserts(callable $work): int
{
    DB::enableQueryLog();
    $work();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return collect($log)
        ->filter(fn ($q) => str_contains($q['query'], 'insert into "journal_entries"'))
        ->count();
}

test('storing an order posts its ledger once, not once per item', function () {
    // Each item save used to re-post the whole order from scratch, so writing
    // N items wrote the entry set N times over — quadratic in the item count.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $inserts = countEntryInserts(fn () => $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => tenItems(),
    ])->assertRedirect(route('orders.index')));

    // Ten dates means ten entries. Anything much above that is re-posting.
    expect($inserts)->toBeLessThanOrEqual(12);
});

test('updating an order re-posts its ledger once, not once per item', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);
    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => tenItems(),
    ]);
    $order = Order::sole();

    $inserts = countEntryInserts(fn () => $this->put(route('orders.update', $order), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => tenItems(),
    ]));

    expect($inserts)->toBeLessThanOrEqual(12);
});

test('the ledger a batched order leaves behind is still exactly right', function () {
    // The point of the change is fewer writes, not different books.
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. أحمد']);

    $this->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => tenItems(),
    ]);

    $reports = app(LedgerReports::class);

    expect(JournalEntry::count())->toBe(10)
        ->and(JournalLine::count())->toBe(20)
        ->and($reports->balance(AccountCode::REVENUE->value))->toBe(1000)
        ->and($reports->receivablesByDentist()[$dentist->id])->toBe(1000)
        ->and(Order::sole()->amount)->toBe(1000);

    // One entry on each item's own date, oldest first.
    expect(JournalEntry::orderBy('entry_date')->pluck('entry_date')->map->toDateString()->all())
        ->toBe(collect(range(1, 10))->map(fn ($i) => sprintf('2026-08-%02d', $i))->all());
});
