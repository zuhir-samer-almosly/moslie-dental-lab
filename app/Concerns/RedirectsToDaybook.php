<?php

namespace App\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * The daybook saves through the ordinary order and payment actions, on its
 * own routes. Those actions used to send everyone to their list page; a save
 * made from the daybook goes back to the daybook instead.
 */
trait RedirectsToDaybook
{
    protected function redirectAfterSave(string $route): RedirectResponse
    {
        return request()->routeIs('daybook.*')
            ? redirect()->back(fallback: route('daybook.index'))
            : redirect()->route($route);
    }
}
