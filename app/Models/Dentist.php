<?php

namespace App\Models;

use App\Observers\CascadeLedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CascadeLedgerObserver::class)]
class Dentist extends Model
{
    /** @use HasFactory<\Database\Factories\DentistFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'gender',
        'phone',
        'address',
        'price_list',
    ];

    protected $casts = [
        'price_list' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(DentistPayment::class);
    }
}
