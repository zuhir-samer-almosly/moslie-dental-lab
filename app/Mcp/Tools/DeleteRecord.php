<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\DentistController;
use App\Http\Controllers\DentistPaymentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePaymentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MaterialPurchaseController;
use App\Http\Controllers\OrderController;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Permanently delete one record and its ledger entry. Only for removing something entered by mistake, and only after the user has confirmed that exact record — to fix a wrong value use the matching save tool with the id, and to void an order set its status to cancelled. A dentist with orders or payments, or an employee with payments, cannot be deleted.')]
#[IsDestructive]
class DeleteRecord extends FormTool
{
    /** type => [model, controller] */
    private const TYPES = [
        'order' => [Order::class, OrderController::class],
        'dentist_payment' => [DentistPayment::class, DentistPaymentController::class],
        'expense' => [Expense::class, ExpenseController::class],
        'material_purchase' => [MaterialPurchase::class, MaterialPurchaseController::class],
        'employee_payment' => [EmployeePayment::class, EmployeePaymentController::class],
        'dentist' => [Dentist::class, DentistController::class],
        'employee' => [Employee::class, EmployeeController::class],
    ];

    public function handle(Request $request): Response
    {
        $request->validate(['type' => ['required', Rule::in(array_keys(self::TYPES))]]);

        [$model, $controller] = self::TYPES[$request->get('type')];
        $row = $this->find($model, $request->get('id'));

        $this->run(fn () => app($controller)->destroy($row));

        return Response::json(['deleted' => $request->get('type'), 'id' => $row->id]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_keys(self::TYPES))->required(),
            'id' => $schema->integer()->required(),
        ];
    }
}
