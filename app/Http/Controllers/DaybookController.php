<?php

namespace App\Http\Controllers;

use App\Concerns\ParsesDate;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Order;
use App\Money\Rate;
use App\Support\OrderPeriod;
use App\Support\PdfRenderer;
use App\Support\WorkTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * اليومية — the lab's daily sheet, one day at a time, built for a tablet.
 *
 * This controller only READS. A line is saved through the ordinary order and
 * payment actions (see the `daybook.*` routes), so validation, currency
 * handling and ledger posting stay where they already are. Each work line is
 * its own one-item order, which is what makes a line editable and deletable
 * on its own, like a line on paper.
 */
class DaybookController extends Controller
{
    use ParsesDate;

    public function index(Request $request)
    {
        $date = $this->date($request);

        return inertia('daybook/index', [
            'date' => $date,
            'today' => now()->toDateString(),
            'lines' => $this->lines($date),
            'dentists' => Dentist::orderBy('name')->get(['id', 'name', 'currency', 'price_list']),
            'workTypes' => WorkTypes::forOrderForm(),
            'todayRate' => Rate::on(now()->toDateString()),
        ]);
    }

    /** The day's sheet without app chrome, rendered by headless Chromium. */
    public function printView(Request $request)
    {
        $date = $this->date($request);

        return inertia('daybook/print', [
            'date' => $date,
            'lines' => $this->lines($date),
        ]);
    }

    public function pdf(Request $request)
    {
        $date = $this->date($request);

        return response(
            PdfRenderer::render('daybook.print-view', ['date' => $date]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="daybook-'.$date.'.pdf"',
            ],
        );
    }

    private function date(Request $request): string
    {
        return $this->parseDate($request->query('date')) ?? now()->toDateString();
    }

    /**
     * Everything written on one day, in the order it was entered: each work
     * item dated that day and each payment received that day.
     *
     * `value` is the line's money in its dentist's own currency (`currency`):
     * whole lira, or cents for a dollar dentist.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function lines(string $date): Collection
    {
        $orders = Order::billable()
            ->with(['dentist:id,name,currency', 'items'])
            ->withCount('items')
            // An item is dated by its own `meta.date`, falling back to the
            // order's due date. This narrows the query; OrderPeriod::scope
            // below is what decides exactly which items belong to the day.
            ->where(fn ($query) => $query
                ->whereDate('due_date', $date)
                ->orWhereHas('items', fn ($items) => $items->where('meta->date', $date)))
            ->get();

        $work = $orders
            ->map(fn (Order $order) => OrderPeriod::scope($order, $date, $date))
            ->filter()
            ->flatMap(fn (Order $order) => $order->items->map(function ($item) use ($order) {
                // Answers OrderItem::nativeCurrency() from memory.
                $item->setRelation('order', $order);

                return [
                    'kind' => 'work',
                    'key' => 'work-'.$item->id,
                    'order_id' => $order->id,
                    'status' => $order->status,
                    // Saving a line replaces its order's items, so only an
                    // order that IS this one line may be edited from here.
                    'editable' => $order->items_count === 1,
                    'dentist_id' => $order->dentist_id,
                    'dentist_name' => $order->dentist?->name,
                    'currency' => $order->billingCurrency()->value,
                    'value' => $item->quantity * $item->valueInOwnCurrency(),
                    'type' => $item->type,
                    'patient_name' => $item->meta['patient_name'] ?? '',
                    'selected_teeth' => $item->meta['selected_teeth'] ?? [],
                    'quantity' => (int) $item->quantity,
                    'price' => (int) $item->price,
                    'item_currency' => $item->currency ?? 'SYP',
                    'original_amount' => $item->original_amount,
                    'rate' => $item->rate,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at?->toIso8601String(),
                ];
            }));

        $payments = DentistPayment::with('dentist:id,name,currency')
            ->whereDate('payment_date', $date)
            ->get()
            ->map(fn (DentistPayment $payment) => [
                'kind' => 'payment',
                'key' => 'payment-'.$payment->id,
                'payment_id' => $payment->id,
                'editable' => true,
                'dentist_id' => $payment->dentist_id,
                'dentist_name' => $payment->dentist?->name,
                'currency' => $payment->dentist?->billingCurrency()->value ?? 'SYP',
                'value' => $payment->valueInOwnCurrency(),
                'amount' => (int) $payment->amount,
                'payment_currency' => $payment->currency ?? 'SYP',
                'original_amount' => $payment->original_amount,
                'rate' => $payment->rate,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at?->toIso8601String(),
            ]);

        return $work->concat($payments)->sortBy('created_at')->values();
    }
}
