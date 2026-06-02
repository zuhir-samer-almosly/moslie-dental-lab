<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
