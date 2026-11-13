<?php

namespace App\Mcp\Tools;

use App\Money\Rate;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * A tool that writes by submitting the same form the web UI submits.
 *
 * The rules for a write live in the app's FormRequests and controllers —
 * currency handling, canonical work types, deferred ledger posting. A tool
 * that called the models directly would be a second copy of all that, free to
 * drift. So a tool only translates its arguments into the form's payload and
 * hands it to the real request and the real controller action.
 */
abstract class FormTool extends Tool
{
    /**
     * Build and validate a FormRequest from tool arguments, as if the form
     * had been posted. Throws ValidationException, which the MCP layer turns
     * into an error result the model can read and correct.
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $parameters  route parameters the request reads (`$this->route('dentist')`)
     * @return T
     */
    protected function form(string $class, array $data, array $parameters = []): FormRequest
    {
        $data = array_filter($data, fn ($value) => $value !== null);

        $request = $class::create('/', 'POST', $data);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setRouteResolver(function () use ($request, $parameters) {
            $route = (new Route('POST', '/', []))->bind($request);

            foreach ($parameters as $name => $value) {
                $route->setParameter($name, $value);
            }

            return $route;
        });

        $request->validateResolved();

        return $request;
    }

    /**
     * Run a controller action. Controllers answer a refused write (deleting a
     * dentist who has orders) with a flashed `error` rather than an
     * exception; surface that as a failure instead of reporting success.
     *
     * @param  Closure(): mixed  $action
     */
    protected function run(Closure $action): void
    {
        // The stdio server is one long-lived process: without this, a
        // refusal flashed by an earlier call would fail every later one.
        session()->forget(['error', 'success']);

        $response = $action();

        $error = $response instanceof RedirectResponse ? session()->get('error') : null;

        if ($error) {
            throw ValidationException::withMessages(['record' => $error]);
        }
    }

    /**
     * Run a controller `store` action and return the row it created — the
     * controllers redirect rather than return the model.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @param  Closure(): mixed  $action
     * @return T
     */
    protected function created(string $model, Closure $action): Model
    {
        $before = (int) $model::query()->max('id');

        $this->run($action);

        return $model::query()->where('id', '>', $before)->orderBy('id')->firstOrFail();
    }

    /**
     * Run a store or an update, whichever the presence of an id asks for,
     * and return the saved row.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @param  Closure(): mixed  $store
     * @param  Closure(T): mixed  $update
     * @return T
     */
    protected function save(string $model, mixed $id, Closure $store, Closure $update): Model
    {
        if ($id === null) {
            return $this->created($model, $store);
        }

        $row = $this->find($model, $id);
        $this->run(fn () => $update($row));

        return $row->refresh();
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    protected function find(string $model, mixed $id, string $key = 'id'): Model
    {
        $row = is_numeric($id) ? $model::find((int) $id) : null;

        if (! $row) {
            throw ValidationException::withMessages([
                $key => 'No '.class_basename($model).' with id '.json_encode($id).'.',
            ]);
        }

        return $row;
    }

    /** A `Y-m-d` date from an argument, today when it was left out. */
    protected function day(mixed $value, string $key): string
    {
        try {
            return $value ? Carbon::parse((string) $value)->toDateString() : now()->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$key => "'{$value}' is not a date; use YYYY-MM-DD."]);
        }
    }

    /**
     * The `amount` / `usd_amount` / `rate` arguments every money tool takes.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function moneySchema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->integer()->description('The amount in whole Syrian lira. Give this OR usd_amount.'),
            'usd_amount' => $schema->number()->description('The amount in US dollars (e.g. 150 or 12.5), when the money was in dollars.'),
            'rate' => $schema->number()->description('Lira per one dollar, for a usd_amount. Defaults to the rate in effect on the entry date. Never used for a dollar-billed dentist.'),
        ];
    }

    /**
     * The money fields of a payment/expense form, from the tool's
     * `amount` (whole lira) / `usd_amount` (dollars) / `rate` arguments.
     *
     * @param  bool  $dollarOwner  the row belongs to a dollar dentist, whose money is never converted
     * @return array<string, mixed>
     */
    protected function money(Request $request, string $date, bool $dollarOwner = false): array
    {
        $lira = $request->get('amount');
        $dollars = $request->get('usd_amount');

        if ($lira !== null && $dollars !== null) {
            throw ValidationException::withMessages([
                'amount' => 'Give either amount (lira) or usd_amount (dollars), not both.',
            ]);
        }

        if ($dollarOwner) {
            if ($dollars === null) {
                throw ValidationException::withMessages([
                    'usd_amount' => 'This dentist is billed in dollars: give usd_amount, not amount.',
                ]);
            }

            return ['currency' => 'USD', 'original_amount' => $dollars];
        }

        if ($dollars === null) {
            return ['currency' => 'SYP', 'amount' => $lira];
        }

        return [
            'currency' => 'USD',
            'original_amount' => $dollars,
            'rate' => $this->rate($request->get('rate'), $date, 'rate'),
        ];
    }

    /**
     * The rate to convert a dollar figure at: the one given, else the rate in
     * effect on the entry's own date — what the web form pre-fills.
     */
    protected function rate(mixed $given, string $date, string $key): string
    {
        $rate = $given ?? Rate::on($date);

        if ($rate === null) {
            throw ValidationException::withMessages([
                $key => "No exchange rate is recorded on or before {$date}; give the rate (lira per dollar) explicitly.",
            ]);
        }

        return (string) $rate;
    }

    /**
     * How a saved money row reads back: the lira it is booked at and, when it
     * arrived as dollars, the dollars and the rate.
     *
     * @return array<string, mixed>
     */
    public static function moneyOf(Model $row, string $liraColumn = 'amount'): array
    {
        if ($row->currency !== 'USD') {
            return ['currency' => 'SYP', 'amount' => (int) $row->{$liraColumn}];
        }

        return array_filter([
            'currency' => 'USD',
            'usd_amount' => $row->original_amount / 100,
            'rate' => $row->rate === null ? null : (float) $row->rate,
            'amount_in_lira' => $row->rate === null ? null : (int) $row->{$liraColumn},
        ], fn ($value) => $value !== null);
    }
}
