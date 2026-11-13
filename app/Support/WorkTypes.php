<?php

namespace App\Support;

use App\Models\Dentist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The names typed into an order line's "type" field.
 *
 * There is no work-types table: a type is whatever text an order line or a
 * dentist's price list carries. That freedom let one product be spelled
 * several ways (إعادة / اعادة, تبيض / تيبض), which splits it in two wherever
 * lines are counted by name. This class is the one place that lists the names
 * in use, snaps a newly typed spelling onto an existing one, and merges two
 * names after the fact.
 *
 * A type is a label only — no ledger entry carries it — so renaming one never
 * moves money.
 */
class WorkTypes
{
    /**
     * Fold the spellings that differ only by hamza/alef form, tashkeel or
     * spacing. Mirrors `normalizeArabic` in resources/js/lib/arabic.ts — the
     * two must agree, or the form would price a line under one spelling and
     * the server would file it under another.
     */
    public static function normalise(string $name): string
    {
        $name = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $name);
        $name = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $name);
        $name = str_replace(['ى', 'ئ'], 'ي', $name);
        $name = str_replace('ؤ', 'و', $name);
        $name = str_replace('ة', 'ه', $name);

        return mb_strtolower(self::tidy($name));
    }

    /** Trim and collapse runs of whitespace; the spelling itself is kept. */
    public static function tidy(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Every name in use, most-used first.
     *
     * @return Collection<int, array{name: string, lines: int, dentists: int, price_lists: int}>
     */
    public static function summary(): Collection
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->groupBy('order_items.type')
            ->selectRaw('order_items.type as name, COUNT(*) as line_count, COUNT(DISTINCT orders.dentist_id) as dentist_count')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->name => [
                'name' => $row->name,
                'lines' => (int) $row->line_count,
                'dentists' => (int) $row->dentist_count,
                'price_lists' => 0,
            ]])
            ->all();

        foreach (Dentist::query()->get(['id', 'price_list']) as $dentist) {
            foreach (array_keys($dentist->price_list ?? []) as $name) {
                $name = (string) $name;
                $rows[$name] ??= ['name' => $name, 'lines' => 0, 'dentists' => 0, 'price_lists' => 0];
                $rows[$name]['price_lists']++;
            }
        }

        return collect($rows)
            ->sort(fn ($a, $b) => [$b['lines'], $a['name']] <=> [$a['lines'], $b['name']])
            ->values();
    }

    /**
     * What an entry form needs to offer types quickly: every name in use, and
     * each dentist's own, most-used first. Shared by the order form and the
     * daybook so both offer exactly the same names.
     *
     * @return array{catalog: list<string>, usage: array<int, list<string>>}
     */
    public static function forOrderForm(): array
    {
        return [
            'catalog' => self::summary()->pluck('name')->all(),
            'usage' => self::usageByDentist(),
        ];
    }

    /**
     * Each dentist's own types, his most-used first — what the order form
     * offers as one-tap buttons.
     *
     * @return array<int, list<string>>
     */
    public static function usageByDentist(): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->groupBy('orders.dentist_id', 'order_items.type')
            ->selectRaw('orders.dentist_id as dentist_id, order_items.type as name, COUNT(*) as line_count')
            ->orderByDesc('line_count')
            ->orderBy('name')
            ->get()
            ->groupBy('dentist_id')
            ->map(fn ($rows) => $rows->pluck('name')->all())
            ->all();
    }

    /**
     * A function that snaps a typed name onto the spelling already in use.
     *
     * The dentist's own price list wins over the wider catalogue, and within
     * the catalogue the most-used spelling wins. A name that matches nothing
     * is kept as typed (tidied) — new types are still allowed.
     *
     * @return \Closure(string): string
     */
    public static function canonicaliser(Dentist $dentist): \Closure
    {
        $known = [];

        $names = [
            ...array_map('strval', array_keys($dentist->price_list ?? [])),
            ...self::summary()->pluck('name')->all(),
        ];

        foreach ($names as $name) {
            $known[self::normalise($name)] ??= $name;
        }

        return function (string $typed) use ($known): string {
            $typed = self::tidy($typed);

            return $known[self::normalise($typed)] ?? $typed;
        };
    }

    /**
     * Fold `$from` into `$to` on every order line and every price list.
     * `$to` need not exist yet, so this is also how a type is renamed.
     *
     * Order lines are updated in one query, deliberately without model
     * events: the ledger observer would re-post every affected order to
     * record a change no ledger entry carries.
     *
     * A dentist who prices both names keeps his `$to` price.
     *
     * @return int how many order lines were renamed
     */
    public static function merge(string $from, string $to): int
    {
        return DB::transaction(function () use ($from, $to) {
            $lines = DB::table('order_items')->where('type', $from)->update(['type' => $to]);

            foreach (Dentist::query()->whereNotNull('price_list')->get() as $dentist) {
                $list = $dentist->price_list ?? [];

                if (! array_key_exists($from, $list)) {
                    continue;
                }

                $merged = [];

                foreach ($list as $name => $entry) {
                    $name = (string) $name;

                    if ($name === $from) {
                        // Keep the row's position, under its new name —
                        // unless the dentist already prices the target.
                        if (! array_key_exists($to, $list)) {
                            $merged[$to] = $entry;
                        }

                        continue;
                    }

                    $merged[$name] = $entry;
                }

                $dentist->price_list = $merged;
                $dentist->save();
            }

            return $lines;
        });
    }

    /**
     * Groups of names that look like one product spelled several ways:
     * the same after normalising, the same words in another order, the same
     * letters in another order (a typing slip), or one letter apart.
     *
     * Suggestions only — nothing merges without the user choosing to.
     *
     * @param  Collection<int, array{name: string, lines: int, dentists: int, price_lists: int}>  $summary
     * @return list<list<array{name: string, lines: int, dentists: int, price_lists: int}>>
     */
    public static function suggestions(Collection $summary): array
    {
        $rows = $summary->values()->all();
        $parent = array_keys($rows);

        $find = function (int $i) use (&$parent): int {
            while ($parent[$i] !== $i) {
                $i = $parent[$i] = $parent[$parent[$i]];
            }

            return $i;
        };

        $normalised = array_map(fn ($row) => self::normalise($row['name']), $rows);

        // Same words in any order; same letters in any order.
        foreach ([' ', ''] as $separator) {
            $seen = [];

            foreach ($normalised as $i => $name) {
                $parts = $separator === ' '
                    ? explode(' ', $name)
                    : mb_str_split(str_replace(' ', '', $name));
                sort($parts);
                $key = implode($separator, $parts);

                if (isset($seen[$key])) {
                    $parent[$find($i)] = $find($seen[$key]);
                } else {
                    $seen[$key] = $i;
                }
            }
        }

        // One letter apart. Short names are left out: قلب and كلب are one
        // letter apart and have nothing to do with each other.
        $letters = array_map(fn ($name) => mb_str_split($name), $normalised);
        $count = count($rows);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (min(count($letters[$i]), count($letters[$j])) >= 5
                    && abs(count($letters[$i]) - count($letters[$j])) <= 1
                    && self::distance($letters[$i], $letters[$j]) === 1) {
                    $parent[$find($i)] = $find($j);
                }
            }
        }

        $groups = [];

        foreach ($rows as $i => $row) {
            $groups[$find($i)][] = $row;
        }

        // $rows arrived most-used first, so each group already is too.
        return array_values(array_filter($groups, fn ($group) => count($group) > 1));
    }

    /**
     * Levenshtein over characters. PHP's own counts bytes, and an Arabic
     * letter is two.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function distance(array $a, array $b): int
    {
        $previous = range(0, count($b));

        foreach ($a as $i => $charA) {
            $current = [$i + 1];

            foreach ($b as $j => $charB) {
                $current[] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($charA === $charB ? 0 : 1),
                );
            }

            $previous = $current;
        }

        return $previous[count($b)];
    }
}
