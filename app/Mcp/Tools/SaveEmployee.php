<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\EmployeeController;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add an employee (omit id) or edit one (give id; fields left out keep their value). To retire an employee set is_active to false rather than deleting them.')]
class SaveEmployee extends FormTool
{
    public function handle(Request $request, EmployeeController $controller): Response
    {
        $existing = $request->get('id') === null ? null : $this->find(Employee::class, $request->get('id'));

        $data = [
            'name' => $request->get('name', $existing?->name),
            'role' => $request->get('role', $existing?->role),
            'phone' => $request->get('phone', $existing?->phone),
            'notes' => $request->get('notes', $existing?->notes),
            'is_active' => $request->get('is_active', $existing?->is_active),
        ];

        $employee = $this->save(
            Employee::class,
            $existing?->id,
            fn () => $controller->store($this->form(StoreEmployeeRequest::class, $data)),
            fn (Employee $employee) => $controller->update($this->form(UpdateEmployeeRequest::class, $data), $employee),
        );

        return Response::json($employee->only(['id', 'name', 'role', 'phone', 'notes', 'is_active']));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The employee to edit. Omit to add a new one.'),
            'name' => $schema->string()->description('Required when adding.'),
            'role' => $schema->string(),
            'phone' => $schema->string(),
            'notes' => $schema->string(),
            'is_active' => $schema->boolean(),
        ];
    }
}
