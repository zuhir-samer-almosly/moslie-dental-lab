<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Divide every stored money figure by a divisor (100, for the currency
 * redenomination).
 *
 * Two things make this more than an UPDATE statement:
 *
 * 1. Every money column is an `integer`. A value that does not divide evenly
 *    cannot be stored, so it would have to be rounded — silently losing
 *    money. This command refuses to touch anything until the whole dataset
 *    is known to divide cleanly, unless --round says otherwise.
 *
 * 2. `orders.amount` is derived: OrderController writes it as
 *    sum(quantity * price) over the order's items. Dividing it directly would
 *    let it drift from its items the moment any price rounds, so it is
 *    recomputed from the already-divided items instead.
 *
 * 3. Native dollar rows — a dollar dentist's orders, items and payments —
 *    are in CENTS, not lira, and this command's divisor is a lira
 *    redenomination. Dividing them would turn $500 into $5. They are
 *    excluded from every column scan and from the derived recomputation.
 *
 * Writes go through the query builder, not Eloquent, so the LedgerObserver
 * does not rewrite journal entries once per row. The ledger is rebuilt from
 * the divided tables afterwards — see the closing hint.
 */
class RedenominateMoney extends Command
{
    use ConfirmableTrait;

    protected $signature = 'money:redenominate
                            {--divisor=100 : Divide every money value by this}
                            {--round : Round values that do not divide evenly (half-up) instead of aborting}
                            {--dry-run : Report what would change without writing anything}
                            {--force : Force the operation to run when in production}';

    protected $description = 'Divide every stored money value by 100 for the currency redenomination';

    /**
     * Plain money columns — divided in place, no invariant to maintain.
     *
     * `orders.amount` is deliberately absent: it is derived from its items.
     *
     * @var list<array{string, string}>
     */
    private const COLUMNS = [
        ['order_items', 'price'],
        ['dentist_payments', 'amount'],
        ['employee_payments', 'amount'],
        ['material_purchases', 'amount'],
        ['expenses', 'amount'],
    ];

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $divisor = (int) $this->option('divisor');

        if ($divisor < 1) {
            $this->error('--divisor must be a positive integer.');

            return self::FAILURE;
        }

        $offenders = $this->findIndivisible($divisor);

        if ($offenders !== [] && ! $this->option('round')) {
            $this->reportOffenders($offenders, $divisor);

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->reportPlan($divisor, $offenders);

            return self::SUCCESS;
        }

        $changed = DB::transaction(fn (): array => $this->apply($divisor));

        $this->reportApplied($changed, $divisor, $offenders);

        return self::SUCCESS;
    }

    /**
     * Every value that would lose money when divided.
     *
     * @return list<array{table: string, id: int|string, key: string, value: int}>
     */
    private function findIndivisible(int $divisor): array
    {
        $offenders = [];

        foreach (self::COLUMNS as [$table, $column]) {
            foreach ($this->rowsNotDivisible($table, $column, $divisor) as $row) {
                $offenders[] = [
                    'table' => $table,
                    'id' => $row->id,
                    'key' => $column,
                    'value' => (int) $row->{$column},
                ];
            }
        }

        // Orders with items get a recomputed amount, so only the item-less
        // ones (legacy rows — `items` is required and min:1 today) divide
        // their own amount and can therefore round.
        foreach ($this->itemlessOrders() as $order) {
            if ((int) $order->amount % $divisor !== 0) {
                $offenders[] = [
                    'table' => 'orders',
                    'id' => $order->id,
                    'key' => 'amount',
                    'value' => (int) $order->amount,
                ];
            }
        }

        foreach ($this->priceListEntries() as [$id, $key, $value]) {
            if ($value % $divisor !== 0) {
                $offenders[] = [
                    'table' => 'dentists.price_list',
                    'id' => $id,
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        return $offenders;
    }

    /** @return \Illuminate\Support\Collection<int, \stdClass> */
    private function rowsNotDivisible(string $table, string $column, int $divisor)
    {
        return $this->excludeNativeDollars(
            DB::table($table)
                ->select('id', $column)
                ->whereRaw("{$column} % ? <> 0", [$divisor])
        )
            ->orderBy('id')
            ->get();
    }

    /**
     * Item-less orders, excluding a native dollar order — `orders` has no
     * `rate` column, so the exclusion is on `currency` alone.
     *
     * Shared by findIndivisible()'s offender scan and divideOrders()'s
     * recomputation, so filtering here protects both at once.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function itemlessOrders()
    {
        return DB::table('orders')
            ->select('id', 'amount')
            ->where('currency', '!=', 'USD')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id'))
            ->orderBy('id')
            ->get();
    }

    /**
     * Native dollar rows hold cents, not lira, so a lira redenomination must
     * not see them. `currency = USD AND rate IS NULL` is precisely the third
     * money state (see App\Concerns\HasForeignCurrency).
     */
    private function excludeNativeDollars(\Illuminate\Database\Query\Builder $query): \Illuminate\Database\Query\Builder
    {
        return $query->where(function ($q) {
            $q->where('currency', '!=', 'USD')->orWhereNotNull('rate');
        });
    }

    /**
     * Flattened price lists: every numeric value across every dentist.
     *
     * @return list<array{int|string, string, int}>
     */
    private function priceListEntries(): array
    {
        $entries = [];

        foreach ($this->dentistsWithPriceList() as $dentist) {
            foreach ($this->decodePriceList($dentist->price_list) as $key => $value) {
                $price = $this->liraPrice($value);

                if ($price !== null) {
                    $entries[] = [$dentist->id, $key, $price];
                }
            }
        }

        return $entries;
    }

    /** @return \Illuminate\Support\Collection<int, \stdClass> */
    private function dentistsWithPriceList()
    {
        return DB::table('dentists')
            ->select('id', 'price_list')
            ->whereNotNull('price_list')
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function decodePriceList(?string $raw): array
    {
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Apply the division. Returns rows touched, keyed by what they were.
     *
     * @return array<string, int>
     */
    private function apply(int $divisor): array
    {
        $changed = [];

        foreach (self::COLUMNS as [$table, $column]) {
            $changed[$table] = $this->divideColumn($table, $column, $divisor);
        }

        $changed['orders'] = $this->divideOrders($divisor);
        $changed['dentists.price_list'] = $this->dividePriceLists($divisor);

        return $changed;
    }

    /**
     * Divide a whole column in one statement.
     *
     * ROUND() is half-up in both MySQL and SQLite for the positive values
     * these columns hold, and every remaining row divides evenly anyway
     * unless --round was passed.
     *
     * The cast target differs by driver — MySQL spells it SIGNED and rejects
     * INTEGER. Tests run on SQLite, so only a real MySQL run proves this
     * branch; keep both spellings.
     */
    private function divideColumn(string $table, string $column, int $divisor): int
    {
        $integer = DB::connection()->getDriverName() === 'mysql' ? 'SIGNED' : 'INTEGER';

        return $this->excludeNativeDollars(DB::table($table))->update([
            $column => DB::raw("CAST(ROUND({$column} / {$divisor}.0) AS {$integer})"),
        ]);
    }

    /**
     * Orders with items are recomputed from those (already divided) items so
     * amount == sum(quantity * price) still holds; item-less orders divide
     * their own amount.
     *
     * A native dollar order's items are excluded from division above, so its
     * sum still comes out to whatever it already was — the `currency` guard
     * here just keeps that explicit rather than relying on it by accident.
     */
    private function divideOrders(int $divisor): int
    {
        $touched = 0;

        $sums = DB::table('order_items')
            ->select('order_id', DB::raw('SUM(quantity * price) as total'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        foreach ($sums as $orderId => $total) {
            $touched += DB::table('orders')
                ->where('id', $orderId)
                ->where('currency', '!=', 'USD')
                ->update(['amount' => (int) $total]);
        }

        foreach ($this->itemlessOrders() as $order) {
            $touched += DB::table('orders')
                ->where('id', $order->id)
                ->update(['amount' => $this->divide((int) $order->amount, $divisor)]);
        }

        return $touched;
    }

    private function dividePriceLists(int $divisor): int
    {
        $touched = 0;

        foreach ($this->dentistsWithPriceList() as $dentist) {
            $list = $this->decodePriceList($dentist->price_list);

            if ($list === []) {
                continue;
            }

            foreach ($list as $key => $value) {
                $price = $this->liraPrice($value);

                if ($price !== null) {
                    $list[$key] = $this->withLiraPrice($value, $this->divide($price, $divisor));
                }
            }

            $touched += DB::table('dentists')
                ->where('id', $dentist->id)
                ->update(['price_list' => json_encode($list, JSON_UNESCAPED_UNICODE)]);
        }

        return $touched;
    }

    /**
     * The lira price inside a price-list entry, or null when there is none to
     * divide.
     *
     * Entries come in two shapes: a bare number, written before prices carried
     * a currency and always meaning lira, and `['price' => n, 'currency' => c]`.
     * A dollar price is not lira — a lira redenomination must leave it exactly
     * where it is.
     */
    private function liraPrice(mixed $entry): ?int
    {
        if (is_array($entry)) {
            return ($entry['currency'] ?? 'SYP') === 'SYP' && is_numeric($entry['price'] ?? null)
                ? (int) $entry['price']
                : null;
        }

        return is_numeric($entry) ? (int) $entry : null;
    }

    /** Put a divided price back in whatever shape its entry already had. */
    private function withLiraPrice(mixed $entry, int $price): mixed
    {
        return is_array($entry) ? ['price' => $price] + $entry : $price;
    }

    private function divide(int $value, int $divisor): int
    {
        return (int) round($value / $divisor, 0, PHP_ROUND_HALF_UP);
    }

    /** @param  list<array{table: string, id: int|string, key: string, value: int}>  $offenders */
    private function reportOffenders(array $offenders, int $divisor): void
    {
        $this->newLine();
        $this->error(sprintf(
            '%d value(s) do not divide evenly by %d:',
            count($offenders),
            $divisor
        ));

        $this->table(
            ['table', 'id', 'field', 'value', 'would become'],
            array_map(fn (array $o): array => [
                $o['table'],
                $o['id'],
                $o['key'],
                number_format($o['value']),
                rtrim(rtrim(number_format($o['value'] / $divisor, 2), '0'), '.'),
            ], $offenders)
        );

        $this->warn('Nothing was changed. Fix these rows first, or re-run with --round to round them half-up.');
    }

    /** @param  list<array{table: string, id: int|string, key: string, value: int}>  $offenders */
    private function reportPlan(int $divisor, array $offenders): void
    {
        $this->newLine();
        $this->info("Dry run — dividing every money value by {$divisor}. Nothing was written.");

        $rows = [];

        foreach (self::COLUMNS as [$table, $column]) {
            $rows[] = [$table.'.'.$column, DB::table($table)->count()];
        }

        $rows[] = ['orders.amount (recomputed from items)', DB::table('orders')->count()];
        $rows[] = ['dentists.price_list', $this->dentistsWithPriceList()->count()];

        $this->table(['target', 'rows'], $rows);

        if ($offenders !== []) {
            $this->warn(sprintf('%d value(s) would be rounded (--round is set).', count($offenders)));
        }

        $this->line('Re-run without --dry-run to apply.');
    }

    /** @param  array<string, int>  $changed */
    private function reportApplied(array $changed, int $divisor, array $offenders): void
    {
        $this->newLine();
        $this->info("Divided every money value by {$divisor}.");

        $this->table(
            ['target', 'rows updated'],
            array_map(fn (string $k, int $v): array => [$k, $v], array_keys($changed), $changed)
        );

        if ($offenders !== []) {
            $this->warn(sprintf('%d value(s) were rounded half-up.', count($offenders)));
        }

        $this->newLine();
        $this->comment('The ledger still holds the old figures. Rebuild it now:');
        $this->line('  php artisan ledger:rebuild --force');
    }
}
