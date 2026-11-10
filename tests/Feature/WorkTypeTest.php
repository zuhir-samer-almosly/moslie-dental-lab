<?php

use App\Models\Dentist;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\WorkTypes;

function workTypeOrder(Dentist $dentist, array $types): Order
{
    test()->post(route('orders.store'), [
        'dentist_id' => $dentist->id,
        'status' => 'pending',
        'items' => array_map(fn (string $type) => [
            'type' => $type, 'quantity' => 1, 'price' => 1000, 'date' => '2026-06-15', 'selected_teeth' => [],
        ], $types),
    ])->assertRedirect(route('orders.index'));

    return Order::latest('id')->first();
}

test('guests cannot see or merge work types', function () {
    $this->get(route('work-types.index'))->assertRedirect(route('login'));
    $this->post(route('work-types.merge'), ['from' => 'أ', 'to' => 'ب'])->assertRedirect(route('login'));
});

test('the page lists every name in use and flags look-alikes', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر', 'price_list' => [
        'ليزر' => ['price' => 1000, 'currency' => 'SYP'],
        'فنير' => ['price' => 2000, 'currency' => 'SYP'],
    ]]);
    workTypeOrder($dentist, ['ليزر', 'ليزر', 'تبيض']);
    // A slip the form cannot catch on its own: two letters swapped. Seeded
    // directly — through the form each would be its own, legitimate, name.
    OrderItem::query()->where('type', 'تبيض')->limit(1)->update(['type' => 'تيبض']);
    workTypeOrder($dentist, ['تبيض']);

    $this->get(route('work-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-types/index')
            ->has('types', 4)
            ->where('types.0', ['name' => 'ليزر', 'lines' => 2, 'dentists' => 1, 'price_lists' => 1])
            // Priced but never ordered still shows, so it can be renamed.
            ->where('types.3', ['name' => 'فنير', 'lines' => 0, 'dentists' => 0, 'price_lists' => 1])
            ->has('suggestions', 1)
            ->where('suggestions.0.0.name', 'تبيض')
            ->where('suggestions.0.1.name', 'تيبض'));
});

test('merging renames order lines and price lists without touching the ledger', function () {
    $this->actingAs(User::factory()->create());
    $both = Dentist::create(['name' => 'د. سامر', 'price_list' => [
        'تيبض' => ['price' => 111, 'currency' => 'SYP'],
        'تبيض' => ['price' => 222, 'currency' => 'SYP'],
    ]]);
    $onlyOld = Dentist::create(['name' => 'د. ليلى', 'price_list' => [
        'ليزر' => ['price' => 500, 'currency' => 'SYP'],
        'تيبض' => ['price' => 333, 'currency' => 'SYP'],
    ]]);
    // Typed freehand for a third dentist, so the misspelling reaches a line.
    $order = workTypeOrder(Dentist::create(['name' => 'د. نور']), ['تيبض', 'ليزر']);
    $entries = JournalEntry::with('lines')->get()->toArray();

    $this->from(route('work-types.index'))
        ->post(route('work-types.merge'), ['from' => 'تيبض', 'to' => 'تبيض'])
        ->assertRedirect(route('work-types.index'))
        ->assertSessionHas('success');

    expect($order->items()->pluck('type')->all())->toBe(['تبيض', 'ليزر'])
        // Priced both: the target's own price stands.
        ->and($both->fresh()->price_list)->toBe(['تبيض' => ['price' => 222, 'currency' => 'SYP']])
        // Priced only the old name: same price, same position, new name.
        ->and(array_keys($onlyOld->fresh()->price_list))->toBe(['ليزر', 'تبيض'])
        ->and($onlyOld->fresh()->price_list['تبيض']['price'])->toBe(333)
        ->and(JournalEntry::with('lines')->get()->toArray())->toBe($entries);
});

test('a merge needs a different, existing source name', function () {
    $this->actingAs(User::factory()->create());
    workTypeOrder(Dentist::create(['name' => 'د. سامر']), ['ليزر']);

    $this->post(route('work-types.merge'), ['from' => 'ليزر', 'to' => ' ليزر '])
        ->assertSessionHasErrors('to');
    $this->post(route('work-types.merge'), ['from' => 'غير موجود', 'to' => 'ليزر'])
        ->assertSessionHasErrors('from');
});

test('an order line is filed under the spelling already in use', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر', 'price_list' => [
        'إعادة زركون' => ['price' => 1000, 'currency' => 'SYP'],
    ]]);
    $other = Dentist::create(['name' => 'د. ليلى']);
    workTypeOrder($other, ['صفيحة قاسية']);

    // His own price list's spelling; another dentist's line's spelling; and a
    // name nobody has used, kept as typed apart from the stray spaces.
    $order = workTypeOrder($dentist, ['اعادة  زركون', 'صفيحه قاسيه', '  نوع   جديد ']);

    expect($order->items()->pluck('type')->all())->toBe(['إعادة زركون', 'صفيحة قاسية', 'نوع جديد']);
});

test('the order form receives the catalogue and each dentist\'s usage', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر']);
    workTypeOrder($dentist, ['زركون', 'ليزر', 'ليزر']);

    $this->get(route('orders.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('workTypes.catalog', ['ليزر', 'زركون'])
            ->where("workTypes.usage.{$dentist->id}", ['ليزر', 'زركون']));
});

test('a typed price can be saved into the dentist\'s price list', function () {
    $this->actingAs(User::factory()->create());
    $dentist = Dentist::create(['name' => 'د. سامر', 'price_list' => [
        'ليزر' => ['price' => 500, 'currency' => 'SYP'],
    ]]);

    $this->from(route('orders.create'))
        ->post(route('dentists.price-list.store', $dentist), ['name' => ' فنير ', 'price' => 2500, 'currency' => 'SYP'])
        ->assertRedirect(route('orders.create'))
        ->assertSessionHas('success');

    expect($dentist->fresh()->price_list)->toBe([
        'ليزر' => ['price' => 500, 'currency' => 'SYP'],
        'فنير' => ['price' => 2500, 'currency' => 'SYP'],
    ]);

    $this->post(route('dentists.price-list.store', $dentist), ['name' => 'فنير', 'price' => -1, 'currency' => 'SYP'])
        ->assertSessionHasErrors('price');
});

test('normalising folds hamza, taa marbuta, tashkeel and spacing', function () {
    expect(WorkTypes::normalise(' إعادةُ   زركون '))->toBe('اعاده زركون')
        ->and(WorkTypes::normalise('رصيد آخر فاتورة'))->toBe(WorkTypes::normalise('رصيد اخر فاتوره'));
});
