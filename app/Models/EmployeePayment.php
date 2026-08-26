<?php

namespace App\Models;

use App\Concerns\HasForeignCurrency;
use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(LedgerObserver::class)]
class EmployeePayment extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeePaymentFactory> */
    use HasFactory, HasForeignCurrency;

    protected $fillable = [
        'employee_id',
        'amount',
        'payment_date',
        'notes',
        'currency',
        'original_amount',
        'rate',
    ];

    protected $casts = [
        'original_amount' => 'integer',
        'rate' => 'decimal:6',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
