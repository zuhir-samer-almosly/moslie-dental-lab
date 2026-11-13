<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\DentistController;
use App\Http\Requests\StoreDentistRequest;
use App\Http\Requests\UpdateDentistRequest;
use App\Models\Dentist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add a dentist (omit id) or edit one (give id; fields left out keep their value). Search list-dentists first so the same dentist is not added twice. The billing currency cannot change once the dentist has orders or payments.')]
class SaveDentist extends FormTool
{
    public function handle(Request $request, DentistController $controller): Response
    {
        $existing = $request->get('id') === null ? null : $this->find(Dentist::class, $request->get('id'));

        $data = [
            'name' => $request->get('name', $existing?->name),
            'gender' => $request->get('gender', $existing?->gender),
            'currency' => $request->get('currency'),
            'phone' => $request->get('phone', $existing?->phone),
            'address' => $request->get('address', $existing?->address),
            'price_list' => $this->priceList($request->get('price_list')),
        ];

        $dentist = $this->save(
            Dentist::class,
            $existing?->id,
            fn () => $controller->store($this->form(StoreDentistRequest::class, $data)),
            fn (Dentist $dentist) => $controller->update(
                $this->form(UpdateDentistRequest::class, $data, ['dentist' => $dentist]),
                $dentist,
            ),
        );

        return Response::json([
            'id' => $dentist->id,
            'name' => $dentist->name,
            'gender' => $dentist->gender,
            'currency' => $dentist->currency,
            'phone' => $dentist->phone,
            'address' => $dentist->address,
            'price_list' => $dentist->price_list,
        ]);
    }

    /**
     * The tool's list of `{type, currency, price}` as the form's
     * `name => {price, currency}` map, dollars turned into the cents it holds.
     *
     * @return array<string, array{price: mixed, currency: string}>|null
     */
    private function priceList(mixed $entries): ?array
    {
        if ($entries === null) {
            return null;
        }

        $list = [];

        foreach ((array) $entries as $index => $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['price'])) {
                throw ValidationException::withMessages([
                    "price_list.{$index}" => 'Each price needs a type and a price.',
                ]);
            }

            $dollars = ($entry['currency'] ?? 'SYP') === 'USD';

            $list[(string) $entry['type']] = [
                'price' => $dollars ? (int) round(((float) $entry['price']) * 100) : $entry['price'],
                'currency' => $dollars ? 'USD' : 'SYP',
            ];
        }

        return $list;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The dentist to edit. Omit to add a new one.'),
            'name' => $schema->string()->description('Required when adding.'),
            'gender' => $schema->string()->enum(['male', 'female'])->description('Required when adding.'),
            'currency' => $schema->string()->enum(['SYP', 'USD'])
                ->description('What the dentist is billed and pays in. Default SYP. USD means every order and payment of theirs is in dollars.'),
            'phone' => $schema->string()->description('Must be unique among dentists.'),
            'address' => $schema->string(),
            'price_list' => $schema->array()->items($schema->object([
                'type' => $schema->string()->required()->description('Work type name.'),
                'currency' => $schema->string()->enum(['SYP', 'USD'])->description('Default SYP.'),
                'price' => $schema->number()->required()->description('Per unit: whole lira for SYP, dollars (e.g. 12.5) for USD.'),
            ]))->description('The dentist\'s own prices. When given it REPLACES the whole list, so include the entries to keep.'),
        ];
    }
}
