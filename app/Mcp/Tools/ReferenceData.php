<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Models\Employee;
use App\Money\Rate;
use App\Support\WorkTypes;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('The lookups needed for data entry: today\'s date, the current lira-per-dollar exchange rate, employees (with ids), expense categories (key and Arabic name) and every work type name in use.')]
#[IsReadOnly]
class ReferenceData extends Tool
{
    public function handle(Request $request): Response
    {
        $today = now()->toDateString();
        $rate = Rate::on($today);

        return Response::json([
            'today' => $today,
            'exchange_rate' => $rate === null ? null : (float) $rate,
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name', 'role', 'is_active']),
            'expense_categories' => Account::expenseCategories()
                ->map(fn (Account $account) => ['key' => $account->category_key, 'name' => $account->name])
                ->all(),
            'work_types' => WorkTypes::summary()->pluck('name')->all(),
        ]);
    }
}
