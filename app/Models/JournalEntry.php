<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class JournalEntry extends Model
{
    protected $fillable = ['entry_date', 'description', 'source_type', 'source_id'];

    protected $casts = ['entry_date' => 'date'];

    /**
     * Ordered by id — insertion order, which is the order the posting rule
     * wrote the sides in (debit first). An accounting document should not
     * render its two sides in whatever order the database happens to return;
     * SQLite and InnoDB both give PK order today, but neither promises it.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Force storage to a bare `Y-m-d` on every driver. The `date` cast above
     * still governs reads; without this mutator, SQLite (which has no real
     * DATE type to truncate on insert, unlike MySQL) stores the full
     * `Y-m-d H:i:s` connection format, so raw `DB::table()` reads and
     * string-bound `<=`/`whereBetween` comparisons in LedgerReports would
     * see a driver-dependent value and silently drop entries dated exactly
     * on a boundary.
     */
    protected function entryDate(): Attribute
    {
        return Attribute::set(fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString());
    }
}
