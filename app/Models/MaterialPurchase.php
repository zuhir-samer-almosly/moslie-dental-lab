<?php

namespace App\Models;

use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(LedgerObserver::class)]
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
