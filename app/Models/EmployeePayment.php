<?php

namespace App\Models;

use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(LedgerObserver::class)]
class EmployeePayment extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'amount',
        'payment_date',
        'notes',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
