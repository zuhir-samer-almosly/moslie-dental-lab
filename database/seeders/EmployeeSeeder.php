<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeePayment;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Employee::factory(4)
            ->create()
            ->each(function (Employee $employee) {
                EmployeePayment::factory(rand(1, 3))->create([
                    'employee_id' => $employee->id,
                ]);
            });
    }
}
