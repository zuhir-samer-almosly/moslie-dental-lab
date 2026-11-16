<?php

use App\Models\Dentist;
use Illuminate\Support\Facades\DB;

test('a price list written before currencies reads as lira', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد']);
    DB::table('dentists')->where('id', $dentist->id)
        ->update(['price_list' => json_encode(['زيركون' => 250], JSON_UNESCAPED_UNICODE)]);

    expect($dentist->fresh()->price_list)
        ->toBe(['زيركون' => ['price' => 250, 'currency' => 'SYP']]);
});

test('a dollar price keeps its currency', function () {
    // Dollar prices are held in cents, the way payments hold theirs.
    $dentist = Dentist::create([
        'name' => 'د. أحمد',
        'price_list' => ['خزف' => ['price' => 17_00, 'currency' => 'USD']],
    ]);

    expect($dentist->fresh()->price_list)
        ->toBe(['خزف' => ['price' => 1700, 'currency' => 'USD']]);
});

test('one price list holds both currencies at once', function () {
    $dentist = Dentist::create([
        'name' => 'د. أحمد',
        'price_list' => [
            'خزف' => ['price' => 17_00, 'currency' => 'USD'],
            'زيركون' => ['price' => 250, 'currency' => 'SYP'],
        ],
    ]);

    expect($dentist->fresh()->price_list)->toBe([
        'خزف' => ['price' => 1700, 'currency' => 'USD'],
        'زيركون' => ['price' => 250, 'currency' => 'SYP'],
    ]);
});

test('an empty price list stays empty', function () {
    $dentist = Dentist::create(['name' => 'د. أحمد', 'price_list' => []]);

    expect($dentist->fresh()->price_list)->toBe([]);
});
