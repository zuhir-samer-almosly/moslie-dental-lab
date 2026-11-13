<?php

namespace App\Mcp\Tools;

use App\Models\DentistPayment;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List saved records, newest first — to check what was entered, avoid entering something twice, or find the id of a record to correct or delete.')]
#[IsReadOnly]
class ListRecords extends Tool
{
    /** type => [model, date column, relations] */
    private const TYPES = [
        'orders' => [Order::class, 'due_date', ['dentist:id,name,currency', 'items']],
        'dentist_payments' => [DentistPayment::class, 'payment_date', ['dentist:id,name,currency']],
        'expenses' => [Expense::class, 'expense_date', []],
        'material_purchases' => [MaterialPurchase::class, 'purchase_date', []],
        'employee_payments' => [EmployeePayment::class, 'payment_date', ['employee:id,name']],
    ];

    public function handle(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'dentist_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        [$model, $date, $relations] = self::TYPES[$filters['type']];
        $ofDentist = in_array($filters['type'], ['orders', 'dentist_payments'], true);

        $rows = $model::query()
            ->with($relations)
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate($date, '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate($date, '<=', $to))
            ->when($ofDentist ? ($filters['dentist_id'] ?? null) : null, fn ($q, $id) => $q->where('dentist_id', $id))
            ->when($filters['type'] === 'employee_payments' ? ($filters['employee_id'] ?? null) : null, fn ($q, $id) => $q->where('employee_id', $id))
            ->orderByDesc($date)
            ->orderByDesc('id')
            ->limit($filters['limit'] ?? 30)
            ->get();

        return Response::json($rows->map(fn (Model $row) => self::present($row))->all());
    }

    /**
     * One record as the tools report it — also what every save tool answers
     * with, so a write reads back in the same shape a listing shows.
     *
     * @return array<string, mixed>
     */
    public static function present(Model $row): array
    {
        $day = fn ($value) => $value === null ? null : substr((string) $value, 0, 10);

        return match (true) {
            $row instanceof Order => [
                'id' => $row->id,
                'dentist_id' => $row->dentist_id,
                'dentist' => $row->dentist?->name,
                'date' => $row->due_date?->toDateString(),
                'status' => $row->status,
                'notes' => $row->notes,
                'currency' => $row->currency,
                // Dollars for a dollar dentist, whole lira otherwise.
                'total' => $row->currency === 'USD' ? $row->valueInOwnCurrency() / 100 : $row->valueInOwnCurrency(),
                'items' => $row->items->map(fn ($item) => array_filter([
                    'type' => $item->type,
                    'quantity' => $item->quantity,
                    'date' => $item->meta['date'] ?? null,
                    'patient_name' => ($item->meta['patient_name'] ?? '') ?: null,
                    'selected_teeth' => ($item->meta['selected_teeth'] ?? []) ?: null,
                    'notes' => $item->notes,
                    'unit_price' => FormTool::moneyOf($item, 'price'),
                ], fn ($value) => $value !== null))->all(),
            ],
            $row instanceof DentistPayment => [
                'id' => $row->id,
                'dentist_id' => $row->dentist_id,
                'dentist' => $row->dentist?->name,
                'date' => $day($row->payment_date),
                ...FormTool::moneyOf($row),
                'notes' => $row->notes,
            ],
            $row instanceof EmployeePayment => [
                'id' => $row->id,
                'employee_id' => $row->employee_id,
                'employee' => $row->employee?->name,
                'date' => $day($row->payment_date),
                ...FormTool::moneyOf($row),
                'notes' => $row->notes,
            ],
            $row instanceof Expense => [
                'id' => $row->id,
                'category' => $row->category,
                'description' => $row->description,
                'date' => $day($row->expense_date),
                ...FormTool::moneyOf($row),
                'notes' => $row->notes,
            ],
            $row instanceof MaterialPurchase => [
                'id' => $row->id,
                'name' => $row->name,
                'supplier' => $row->supplier,
                'quantity' => $row->quantity,
                'date' => $day($row->purchase_date),
                ...FormTool::moneyOf($row),
                'notes' => $row->notes,
            ],
            default => $row->toArray(),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_keys(self::TYPES))->required(),
            'from' => $schema->string()->description('Earliest date, YYYY-MM-DD.'),
            'to' => $schema->string()->description('Latest date, YYYY-MM-DD.'),
            'dentist_id' => $schema->integer()->description('Only this dentist (orders and dentist_payments).'),
            'employee_id' => $schema->integer()->description('Only this employee (employee_payments).'),
            'limit' => $schema->integer()->description('How many rows, 1-100. Default 30.'),
        ];
    }
}
