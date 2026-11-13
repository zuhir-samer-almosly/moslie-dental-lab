<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ExpenseController;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record a general expense such as rent or electricity (omit id), or correct an existing one (give id, and every field again). Materials bought for the lab go through save-material-purchase, salaries through save-employee-payment.')]
class SaveExpense extends FormTool
{
    public function handle(Request $request, ExpenseController $controller): Response
    {
        $date = $this->day($request->get('expense_date'), 'expense_date');

        $data = [
            'category' => $request->get('category'),
            'description' => $request->get('description'),
            'expense_date' => $date,
            'notes' => $request->get('notes'),
            ...$this->money($request, $date),
        ];

        $expense = $this->save(
            Expense::class,
            $request->get('id'),
            fn () => $controller->store($this->form(StoreExpenseRequest::class, $data)),
            fn (Expense $expense) => $controller->update($this->form(UpdateExpenseRequest::class, $data), $expense),
        );

        return Response::json(ListRecords::present($expense));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The expense to correct. Omit to record a new one.'),
            'category' => $schema->string()->required()->description('A category key from reference-data (the key, not the Arabic name).'),
            'description' => $schema->string()->description('What it was for, max 255 characters.'),
            'expense_date' => $schema->string()->description('YYYY-MM-DD. Defaults to today.'),
            ...$this->moneySchema($schema),
            'notes' => $schema->string(),
        ];
    }
}
