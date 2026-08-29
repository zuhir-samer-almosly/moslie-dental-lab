<?php

namespace App\Models;

use App\Money\Currency;
use App\Observers\CascadeLedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[ObservedBy(CascadeLedgerObserver::class)]
class Dentist extends Model
{
    /** @use HasFactory<\Database\Factories\DentistFactory> */
    use HasFactory;

    /**
     * A freshly-instantiated (unsaved) model has no 'currency' key in its
     * attribute array unless one is given explicitly — mass assignment only
     * sets what's passed. Mirrors the migration's own `default('SYP')` so a
     * brand-new in-memory instance already agrees with what the row will get
     * once saved.
     */
    protected $attributes = [
        'currency' => 'SYP',
    ];

    protected $fillable = [
        'name',
        'currency',
        'gender',
        'phone',
        'address',
        'price_list',
    ];

    /**
     * A dentist's own prices, as `name => ['price' => int, 'currency' => str]`.
     *
     * The unit of `price` follows its currency, the same way payments work:
     * whole lira for SYP, cents for USD. A dollar price is a *quote* — it
     * converts to lira at the rate of the day an order uses it, and the order
     * holds lira from then on.
     *
     * Rows written before currencies existed hold a bare number, which meant
     * lira; they are normalised on read so nothing downstream has to know
     * about the old shape.
     */
    protected function priceList(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : self::normalisePriceList(json_decode($value, true) ?? []),
            set: function (?array $value) {
                if ($value === null) {
                    return null;
                }

                $list = self::normalisePriceList($value);

                // A dollar dentist is quoted in dollars, full stop. Forced
                // here rather than in normalisePriceList, which is static and
                // has no dentist to ask.
                if ($this->isDollar()) {
                    $list = array_map(
                        fn (array $entry) => ['price' => $entry['price'], 'currency' => 'USD'],
                        $list,
                    );
                }

                return json_encode($list, JSON_UNESCAPED_UNICODE);
            },
        );
    }

    /**
     * @param  array<string, mixed>  $list
     * @return array<string, array{price: int, currency: string}>
     */
    public static function normalisePriceList(array $list): array
    {
        $normalised = [];

        foreach ($list as $name => $entry) {
            if (is_array($entry)) {
                $normalised[$name] = [
                    'price' => (int) ($entry['price'] ?? 0),
                    'currency' => ($entry['currency'] ?? 'SYP') === 'USD' ? 'USD' : 'SYP',
                ];

                continue;
            }

            $normalised[$name] = ['price' => (int) $entry, 'currency' => 'SYP'];
        }

        return $normalised;
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(DentistPayment::class);
    }

    /**
     * The currency this dentist is quoted, billed and paid in.
     *
     * Named `billingCurrency()` rather than `currency()` on purpose: a method
     * named exactly like the `currency` column collides with Eloquent's
     * relationship resolution the moment a hydrated row is missing that
     * column (e.g. a narrowed `select()`) — `newFromBuilder()` replaces
     * `$attributes` wholesale, so the class-level default below does not
     * cover it, and `$this->currency` then falls through to "is this a
     * relationship method?" and throws a confusing LogicException. The
     * rename avoids the collision outright; the guard below keeps failure
     * loud instead of silently defaulting a dollar dentist's money to lira.
     */
    public function billingCurrency(): Currency
    {
        // A partial select that omits the column leaves this unknowable. Falling
        // back to SYP here would post a dollar dentist's money to the lira
        // accounts — silently. For money, failing loudly is the safer answer.
        if (! array_key_exists('currency', $this->getAttributes())) {
            throw new \LogicException(
                'Dentist #'.$this->id.' was loaded without its `currency` column, so its '
                .'billing currency is unknowable. Select the column, or do not ask.'
            );
        }

        return Currency::from($this->currency ?? Currency::SYP->value);
    }

    public function isDollar(): bool
    {
        return $this->billingCurrency() === Currency::USD;
    }

    /**
     * Whether anything has been posted to this dentist's account yet.
     *
     * Guards the currency flag: switching a dentist mid-history would leave
     * his old entries in one currency's receivable account and his new ones
     * in another, with no defensible way to read a single balance. Creating a
     * dollar dentist is supported; converting one is not.
     */
    public function hasLedgerLines(): bool
    {
        return DB::table('journal_lines')->where('dentist_id', $this->id)->exists();
    }
}
