<?php

namespace App\Mcp\Tools;

use App\Ledger\LedgerReports;
use App\Models\Dentist;
use App\Money\Currency;
use App\Support\WorkTypes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Find dentists by name or phone (or list them all). Returns each dentist\'s id, billing currency, current balance owed, price list and most-used work types. Call this first to resolve a dentist name to the dentist_id every other tool needs.')]
#[IsReadOnly]
class ListDentists extends Tool
{
    public function handle(Request $request, LedgerReports $reports): Response
    {
        $term = trim((string) $request->get('search', ''));

        $dentists = Dentist::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->orderBy('name')
            ->get();

        $balances = [
            'SYP' => $reports->receivablesByDentist(null, Currency::SYP),
            'USD' => $reports->receivablesByDentist(null, Currency::USD),
        ];
        $usage = WorkTypes::usageByDentist();

        return Response::json($dentists->map(function (Dentist $dentist) use ($balances, $usage) {
            $owed = (int) ($balances[$dentist->currency][$dentist->id] ?? 0);

            return [
                'id' => $dentist->id,
                'name' => $dentist->name,
                'phone' => $dentist->phone,
                'currency' => $dentist->currency,
                // Dollars for a dollar dentist, whole lira otherwise.
                'balance_owed' => $dentist->isDollar() ? $owed / 100 : $owed,
                'price_list' => collect($dentist->price_list ?? [])->map(fn (array $entry, $type) => [
                    'type' => (string) $type,
                    'currency' => $entry['currency'],
                    'price' => $entry['currency'] === 'USD' ? $entry['price'] / 100 : $entry['price'],
                ])->values(),
                'most_used_types' => array_slice($usage[$dentist->id] ?? [], 0, 15),
            ];
        })->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of a name or phone number. Omit to list every dentist.'),
        ];
    }
}
