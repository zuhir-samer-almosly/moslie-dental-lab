<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialPurchase extends Model
{
    /** @use HasFactory<\Database\Factories\MaterialPurchaseFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'supplier',
        'quantity',
        'amount',
        'purchase_date',
        'notes',
    ];
}
