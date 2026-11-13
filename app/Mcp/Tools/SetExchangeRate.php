<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ExchangeRateController;
use App\Http\Requests\StoreExchangeRateRequest;
use App\Money\Rate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Set TODAY\'s lira-per-dollar exchange rate. It is what new dollar entries default to; nothing already booked changes. There is no way to set a past day\'s rate — give a rate on the entry itself instead.')]
#[IsIdempotent]
class SetExchangeRate extends FormTool
{
    public function handle(Request $request, ExchangeRateController $controller): Response
    {
        $this->run(fn () => $controller->store(
            $this->form(StoreExchangeRateRequest::class, ['rate' => $request->get('rate')]),
        ));

        $today = now()->toDateString();

        return Response::json(['date' => $today, 'exchange_rate' => (float) Rate::on($today)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rate' => $schema->number()->required()->description('Syrian lira per one US dollar.'),
        ];
    }
}
