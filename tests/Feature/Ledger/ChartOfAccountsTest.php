<?php

use App\Models\Account;

test('the chart of accounts is seeded by migration', function () {
    expect(Account::count())->toBe(13);
    expect(Account::where('code', '1000')->value('name'))->toBe('الصندوق');
    expect(Account::where('code', '1100')->value('type'))->toBe('asset');
    expect(Account::where('code', '4000')->value('type'))->toBe('revenue');
});

test('expense categories come from accounts that carry a category key', function () {
    $keys = Account::expenseCategories()->pluck('name', 'category_key')->all();

    expect($keys)->toBe([
        'transport' => 'مواصلات وسفر',
        'taxes' => 'ضرائب',
        'rent' => 'إيجار',
        'utilities' => 'كهرباء وماء',
        'maintenance' => 'صيانة',
        'other' => 'أخرى',
    ]);
});

test('idFor resolves a code to its primary key and caches it', function () {
    $id = Account::idFor('1000');

    expect($id)->toBe(Account::where('code', '1000')->value('id'));
    expect(Account::typeFor('5000'))->toBe('expense');
});

test('idFor throws for an unknown code', function () {
    Account::idFor('9999');
})->throws(InvalidArgumentException::class);
