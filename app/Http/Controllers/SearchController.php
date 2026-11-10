<?php

namespace App\Http\Controllers;

use App\Models\Dentist;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The header's search box: jump straight to a dentist or an order.
 *
 * Returns JSON rather than an Inertia page — the box lives in the layout and
 * must answer from any page without navigating away from it.
 */
class SearchController extends Controller
{
    private const DENTIST_LIMIT = 6;

    private const ORDER_LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        // "#42" and "42" both mean order number 42.
        $number = ltrim($term, '#');
        $orderId = ctype_digit($number) ? (int) $number : null;

        // One letter matches half the roster; a bare number is still a
        // complete order reference, however short.
        if ($orderId === null && mb_strlen($term) < 2) {
            return response()->json(['dentists' => [], 'orders' => []]);
        }

        $like = '%'.$term.'%';

        $dentists = Dentist::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like))
            ->orderBy('name')
            ->limit(self::DENTIST_LIMIT)
            ->get(['id', 'name', 'phone']);

        return response()->json([
            'dentists' => $dentists,
            'orders' => $this->orders($like, $orderId),
        ]);
    }

    /**
     * Orders matched by number, by their own or their items' text, or by the
     * dentist's name — so typing a dentist's name also offers his latest work.
     * An exact order number always comes first.
     */
    private function orders(string $like, ?int $orderId): Collection
    {
        $relations = ['dentist:id,name,currency', 'items:id,order_id,type'];

        $matches = Order::with($relations)
            ->where(fn ($q) => $q
                ->where('notes', 'like', $like)
                ->orWhereHas('dentist', fn ($d) => $d->where('name', 'like', $like))
                ->orWhereHas('items', fn ($i) => $i
                    ->where('type', 'like', $like)
                    ->orWhere('notes', 'like', $like)))
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->limit(self::ORDER_LIMIT)
            ->get();

        $exact = $orderId ? Order::with($relations)->find($orderId) : null;

        if ($exact) {
            $matches = $matches->prepend($exact)->unique('id')->take(self::ORDER_LIMIT);
        }

        return $matches->values()->map(fn (Order $order) => [
            'id' => $order->id,
            'dentist' => $order->dentist?->name,
            'due_date' => $order->due_date?->toDateString(),
            'items' => $order->items->pluck('type')->unique()->values(),
            'status' => $order->status,
            // In the order's own currency: cents for a dollar dentist, whole
            // lira otherwise — the pair the frontend's formatMoney expects.
            'amount' => $order->valueInOwnCurrency(),
            'currency' => $order->billingCurrency()->value,
        ]);
    }
}
