<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Expense categories live in the accounts table so the chart of
            // accounts is their single definition. Shared globally because
            // four unrelated pages render them.
            //
            // Two maps, not one: `expenseCategories` (is_active-filtered) is
            // what the UI OFFERS in a picker; `expenseCategoryLabels`
            // (unfiltered) is what the UI LABELS already-recorded data with.
            // A deactivated category must disappear from pickers but keep
            // showing its Arabic name wherever it was already used.
            'expenseCategories' => fn () => Account::expenseCategories()
                ->pluck('name', 'category_key'),
            'expenseCategoryLabels' => fn () => Account::allExpenseCategories()
                ->pluck('name', 'category_key'),
        ];
    }
}
