<?php

namespace App\Models;

use App\Concerns\HasForeignCurrency;
use App\Money\Currency;
use App\Observers\OrderItemLedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(OrderItemLedgerObserver::class)]
class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory, HasForeignCurrency;

    protected $fillable = [
        'order_id',
        'type',
        'quantity',
        'price',
        'notes',
        'meta',
        'currency',
        'original_amount',
        'rate',
    ];

    protected $casts = [
        'meta' => 'array',
        'original_amount' => 'integer',
        'rate' => 'decimal:6',
    ];

    /** This table calls its lira column `price`, not `amount`. */
    protected function liraColumn(): string
    {
        return 'price';
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * An item is native dollars when its order's dentist is a dollar dentist.
     *
     * `$this->order` is a relation read on save. OrderController sets the
     * relation explicitly before saving each item (see its `itemFor()`), so
     * this costs no query on the write path that matters.
     */
    protected function nativeCurrency(): Currency
    {
        return $this->order?->dentist?->billingCurrency() ?? Currency::SYP;
    }
}
