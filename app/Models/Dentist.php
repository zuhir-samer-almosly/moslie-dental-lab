<?php

namespace App\Models;

use App\Observers\CascadeLedgerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CascadeLedgerObserver::class)]
class Dentist extends Model
{
    /** @use HasFactory<\Database\Factories\DentistFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
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
            set: fn (?array $value) => $value === null
                ? null
                : json_encode(self::normalisePriceList($value), JSON_UNESCAPED_UNICODE),
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
}
