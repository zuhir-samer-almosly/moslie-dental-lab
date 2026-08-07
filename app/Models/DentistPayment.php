<?php

namespace App\Models;

use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(LedgerObserver::class)]
class DentistPayment extends Model
{
    /** @use HasFactory<\Database\Factories\DentistPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'dentist_id',
        'amount',
        'payment_date',
    ];

    public function dentist()
    {
        return $this->belongsTo(Dentist::class);
    }
}
