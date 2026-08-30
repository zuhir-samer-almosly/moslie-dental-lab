<?php

namespace App\Models;

use App\Concerns\HasForeignCurrency;
use App\Money\Currency;
use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(LedgerObserver::class)]
class DentistPayment extends Model
{
    /** @use HasFactory<\Database\Factories\DentistPaymentFactory> */
    use HasFactory, HasForeignCurrency;

    protected $fillable = [
        'dentist_id',
        'amount',
        'payment_date',
        'currency',
        'original_amount',
        'rate',
        'notes',
    ];

    protected $casts = [
        'original_amount' => 'integer',
        'rate' => 'decimal:6',
    ];

    public function dentist()
    {
        return $this->belongsTo(Dentist::class);
    }

    protected function nativeCurrency(): Currency
    {
        return $this->dentist?->billingCurrency() ?? Currency::SYP;
    }
}
