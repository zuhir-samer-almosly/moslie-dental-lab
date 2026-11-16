<?php

namespace App\Models;

use App\Money\Currency;
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

    /**
     * Mirrors the migration's own `default('SYP')`, so a brand-new in-memory
     * instance already agrees with what the row will get once saved.
     */
    protected $attributes = [
        'currency' => 'SYP',
    ];

    protected $fillable = [
        'dentist_id',
        'due_date',
        'amount',
        'currency',
        'original_amount',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'due_date' => 'date',
        'original_amount' => 'integer',
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

    /**
     * The currency this order is billed in: its DENTIST's, always.
     *
     * The row's own `currency` column is a copy OrderController writes on
     * every save, and a dentist's currency is frozen once anything has been
     * posted to his account (UpdateDentistRequest) — so the two agree on
     * every real row. Asking the dentist rather than the copy is what keeps
     * the ledger, the invoice and every screen on one answer even for a row
     * built by hand, and it is the same authority `OrderItem` already uses.
     */
    public function billingCurrency(): Currency
    {
        return $this->dentist->billingCurrency();
    }

    /**
     * What this order is worth in its own currency's minor unit — cents for a
     * dollar dentist, whole lira otherwise. What OrderPosting books.
     */
    public function valueInOwnCurrency(): int
    {
        return $this->billingCurrency() === Currency::USD
            ? (int) $this->original_amount
            : (int) $this->amount;
    }
}
