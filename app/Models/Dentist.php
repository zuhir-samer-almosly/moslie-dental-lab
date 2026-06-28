<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dentist extends Model
{
    /** @use HasFactory<\Database\Factories\DentistFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
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
