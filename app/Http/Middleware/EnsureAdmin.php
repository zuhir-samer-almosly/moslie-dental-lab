<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything except the daybook and the dentist notebooks sits behind this.
 * An `entry` login (the tablet in the lab) records work and payments and
 * reads the notebooks; it has no business in finance, expenses or the ledger.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        // Login lands everyone on the dashboard (Fortify's `home`). The entry
        // login has none, so it is sent to its own home instead of a 403.
        if ($request->routeIs('dashboard')) {
            return redirect()->route('daybook.index');
        }

        abort(403);
    }
}
