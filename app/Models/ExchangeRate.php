<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The lira-per-dollar rate the owner recorded for a given day. Rates are
 * reference data for *new* entries only: once a transaction stores the rate it
 * was converted at, editing history here never moves an amount already booked.
 */
class ExchangeRate extends Model
{
    /** @use HasFactory<\Database\Factories\ExchangeRateFactory> */
    use HasFactory;

    protected $fillable = ['rate_date', 'rate'];

    protected $casts = [
        'rate_date' => 'date',
        'rate' => 'decimal:6',
    ];

    /**
     * Force storage to a bare `Y-m-d`, for the same reason JournalEntry does:
     * SQLite has no real DATE type to truncate on insert, so without this the
     * stored value carries a time component and string-bound `<=` comparisons
     * silently miss a rate dated exactly on the boundary being asked about.
     */
    protected function rateDate(): Attribute
    {
        return Attribute::set(fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString());
    }
}
