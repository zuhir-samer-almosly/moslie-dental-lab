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
 * does not fire for the children. This observer runs on `deleting` to capture
 * child IDs while they exist, then removes their entries atomically in
 * `deleted` — after the parent delete succeeds and before control returns.
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

    /** Map of parent identity → list of [child class => ids to forget]. Persists across deleting/deleted. */
    private static array $childrenToForget = [];

    public function __construct(private readonly Ledger $ledger) {}

    public function deleting(Model $parent): void
    {
        $key = $parent::class.':'.$parent->getKey();
        $children = [];

        foreach (self::CHILDREN[$parent::class] ?? [] as $child => $foreignKey) {
            $ids = $child::query()
                ->where($foreignKey, $parent->getKey())
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                $children[$child] = $ids;
            }
        }

        if ($children !== []) {
            self::$childrenToForget[$key] = $children;
        }
    }

    public function deleted(Model $parent): void
    {
        $key = $parent::class.':'.$parent->getKey();

        if (! isset(self::$childrenToForget[$key])) {
            return;
        }

        $children = self::$childrenToForget[$key];
        unset(self::$childrenToForget[$key]);

        foreach ($children as $child => $ids) {
            $this->ledger->forgetMany($child, $ids);
        }
    }
}
