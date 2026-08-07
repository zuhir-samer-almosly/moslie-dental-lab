<?php

namespace App\Observers;

use App\Ledger\Ledger;
use App\Models\Dentist;
use App\Models\DentistPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * Deleting a dentist or an employee cascades to their money rows at the
 * database level, which Eloquent never observes — so LedgerObserver::deleted
 * does not fire for the children. This runs on `deleting`, while the children
 * still exist, and clears their entries by hand.
 */
class CascadeLedgerObserver
{
    /** Parent model → [child model class => foreign key]. */
    private const CHILDREN = [
        Dentist::class => [
            Order::class => 'dentist_id',
            DentistPayment::class => 'dentist_id',
        ],
        Employee::class => [
            EmployeePayment::class => 'employee_id',
        ],
    ];

    public function __construct(private readonly Ledger $ledger) {}

    public function deleting(Model $parent): void
    {
        foreach (self::CHILDREN[$parent::class] ?? [] as $child => $foreignKey) {
            $ids = $child::query()
                ->where($foreignKey, $parent->getKey())
                ->pluck('id')
                ->all();

            $this->ledger->forgetMany($child, $ids);
        }
    }
}
