<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\EmployeePaymentController;
use App\Http\Requests\StoreEmployeePaymentRequest;
use App\Http\Requests\UpdateEmployeePaymentRequest;
use App\Models\Employee;
use App\Models\EmployeePayment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record a salary or other payment made to an employee (omit id), or correct an existing one (give id, and every field again).')]
class SaveEmployeePayment extends FormTool
{
    public function handle(Request $request, EmployeePaymentController $controller): Response
    {
        $employee = $this->find(Employee::class, $request->get('employee_id'), 'employee_id');
        $date = $this->day($request->get('payment_date'), 'payment_date');

        $data = [
            'employee_id' => $employee->id,
            'payment_date' => $date,
            'notes' => $request->get('notes'),
            ...$this->money($request, $date),
        ];

        $payment = $this->save(
            EmployeePayment::class,
            $request->get('id'),
            fn () => $controller->store($this->form(StoreEmployeePaymentRequest::class, $data)),
            fn (EmployeePayment $payment) => $controller->update($this->form(UpdateEmployeePaymentRequest::class, $data), $payment),
        );

        return Response::json(ListRecords::present($payment->load('employee')));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The payment to correct. Omit to record a new one.'),
            'employee_id' => $schema->integer()->required()->description('From reference-data.'),
            'payment_date' => $schema->string()->description('YYYY-MM-DD. Defaults to today.'),
            ...$this->moneySchema($schema),
            'notes' => $schema->string(),
        ];
    }
}
