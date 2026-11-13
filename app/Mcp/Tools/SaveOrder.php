<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\OrderController;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Dentist;
use App\Models\Order;
use App\Support\WorkTypes;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create an order for a dentist (omit id), or replace an existing one (give id — the order\'s items are replaced by the ones given, so send the full list). An order is one or more work lines; the total is derived from them. A line with no price is priced from the dentist\'s price list.')]
class SaveOrder extends FormTool
{
    public function handle(Request $request, OrderController $controller): Response
    {
        $existing = $request->get('id') === null ? null : $this->find(Order::class, $request->get('id'));
        $dentist = $this->find(Dentist::class, $request->get('dentist_id', $existing?->dentist_id), 'dentist_id');
        $items = $request->get('items');

        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages(['items' => 'Give at least one item.']);
        }

        $canonical = WorkTypes::canonicaliser($dentist);

        $data = [
            'dentist_id' => $dentist->id,
            'status' => $request->get('status', $existing?->status ?? 'pending'),
            'notes' => $request->get('notes'),
            'items' => array_map(
                fn ($item, $index) => $this->item($item, $index, $dentist, $canonical),
                array_values($items),
                array_keys(array_values($items)),
            ),
        ];

        $order = $this->save(
            Order::class,
            $existing?->id,
            fn () => $controller->store($this->form(StoreOrderRequest::class, $data)),
            fn (Order $order) => $controller->update($this->form(UpdateOrderRequest::class, $data), $order),
        );

        return Response::json(ListRecords::present($order->load('dentist', 'items')));
    }

    /**
     * One tool line as the order form submits it. The tool takes dollars the
     * way people write them; the form's dollar field is cents.
     *
     * @param  Closure(string): string  $canonical
     * @return array<string, mixed>
     */
    private function item(mixed $item, int $index, Dentist $dentist, Closure $canonical): array
    {
        if (! is_array($item)) {
            throw ValidationException::withMessages(["items.{$index}" => 'Each item must be an object.']);
        }

        $teeth = array_values((array) ($item['selected_teeth'] ?? []));
        $date = $this->day($item['date'] ?? null, "items.{$index}.date");
        $type = (string) ($item['type'] ?? '');

        $line = [
            'type' => $type,
            // As in the form: picking teeth sets the quantity to their count.
            'quantity' => $item['quantity'] ?? (count($teeth) ?: 1),
            'date' => $date,
            'patient_name' => $item['patient_name'] ?? null,
            'selected_teeth' => $teeth,
            'notes' => $item['notes'] ?? null,
        ];

        $lira = $item['price'] ?? null;
        $cents = isset($item['usd_price']) ? (int) round(((float) $item['usd_price']) * 100) : null;

        if ($lira !== null && $cents !== null) {
            throw ValidationException::withMessages([
                "items.{$index}.price" => 'Give either price (lira) or usd_price (dollars), not both.',
            ]);
        }

        if ($lira === null && $cents === null) {
            $listed = ($dentist->price_list ?? [])[$canonical($type)] ?? null;

            if ($listed === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.price" => "No price given, and '{$type}' is not in {$dentist->name}'s price list. Give price (lira) or usd_price (dollars).",
                ]);
            }

            $listed['currency'] === 'USD' ? $cents = $listed['price'] : $lira = $listed['price'];
        }

        if ($dentist->isDollar()) {
            if ($cents === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.usd_price" => "{$dentist->name} is billed in dollars: give usd_price, not price.",
                ]);
            }

            return [...$line, 'original_amount' => $cents];
        }

        if ($cents !== null) {
            return [
                ...$line,
                'currency' => 'USD',
                'original_amount' => $cents,
                'rate' => $this->rate($item['rate'] ?? null, $date, "items.{$index}.rate"),
            ];
        }

        return [...$line, 'currency' => 'SYP', 'price' => $lira];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The order to replace. Omit to create a new order.'),
            'dentist_id' => $schema->integer()->description('From list-dentists. Required when creating.'),
            'status' => $schema->string()->enum(['pending', 'completed', 'cancelled', 'recieved'])
                ->description('Default pending. "recieved" is spelled that way on purpose. A cancelled order bills nothing.'),
            'notes' => $schema->string(),
            'items' => $schema->array()->min(1)->required()->items($schema->object([
                'type' => $schema->string()->required()->description('The work type, in Arabic as the lab writes it (e.g. زيركون). Reuse a name from the dentist\'s price list or most-used types when one fits; a near-identical spelling is snapped onto the existing one.'),
                'quantity' => $schema->integer()->description('Units. Defaults to the number of selected_teeth, or 1.'),
                'date' => $schema->string()->description('The date of this work, YYYY-MM-DD. Defaults to today.'),
                'patient_name' => $schema->string(),
                'selected_teeth' => $schema->array()->items($schema->integer())
                    ->description('FDI tooth numbers: 11-18, 21-28, 31-38, 41-48.'),
                'price' => $schema->integer()->description('Price per unit in whole Syrian lira. Omit both prices to use the dentist\'s price list.'),
                'usd_price' => $schema->number()->description('Price per unit in US dollars (e.g. 12.5). Required instead of price for a dollar-billed dentist unless the price list covers the type.'),
                'rate' => $schema->number()->description('Lira per dollar for a usd_price charged to a lira dentist. Defaults to the rate in effect on the item date.'),
                'notes' => $schema->string(),
            ])),
        ];
    }
}
