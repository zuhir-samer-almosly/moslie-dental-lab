<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A daybook work line is its own one-item order, and saving a line replaces
 * its order's items. So only an order that IS one line may be changed or
 * deleted through the daybook routes.
 *
 * The page already hides the edit and delete buttons on a line that belongs
 * to a larger order; this is the same rule on the server, where a request
 * that never came from the page — the tablet's `entry` login can reach these
 * routes — would otherwise replace a whole order with one line, or delete it.
 * A larger order is edited from the orders section, which is admin-only.
 */
class EnsureDaybookLine
{
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        abort_if(
            $order instanceof Order && $order->items()->count() !== 1,
            403,
            'هذا الطلب يحتوي أكثر من سطر ولا يُعدَّل من اليومية.',
        );

        return $next($request);
    }
}
