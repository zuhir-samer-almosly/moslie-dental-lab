<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'dentist_id',
        'due_date',
        'amount',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'due_date' => 'date',
    ];

    public function dentist()
    {
        return $this->belongsTo(Dentist::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function total(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->items->sum(fn ($item) => $item->quantity * $item->price),
        );
    }
}
