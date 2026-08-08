<?php

namespace App\Models;

use App\Observers\LedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[ObservedBy(LedgerObserver::class)]
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

    /**
     * Force storage to a bare `Y-m-d` on every driver, matching
     * JournalEntry::entryDate(). Without it, SQLite stores the full
     * `Y-m-d H:i:s` connection format for a `date`-cast column (it has no
     * real DATE type to truncate on insert), so an order due on the last day
     * of a month posts to the ledger with a different raw date string than
     * the old single-entry formula reads from this column directly — the
     * two would silently disagree under test even though production (real
     * MySQL DATE columns truncate on insert) is unaffected either way.
     */
    protected function dueDate(): Attribute
    {
        return Attribute::set(fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString());
    }

    public function dentist()
    {
        return $this->belongsTo(Dentist::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Orders that count as money owed. Cancelled orders are excluded from
     * all receivable/invoice/finance totals.
     */
    public function scopeBillable($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    protected function total(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->items->sum(fn ($item) => $item->quantity * $item->price),
        );
    }
}
