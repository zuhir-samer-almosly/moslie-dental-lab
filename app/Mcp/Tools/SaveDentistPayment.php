<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\DentistPaymentController;
use App\Http\Requests\StoreDentistPaymentRequest;
use App\Http\Requests\UpdateDentistPaymentRequest;
use App\Models\Dentist;
use App\Models\DentistPayment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record money received from a dentist (omit id), or correct an existing payment (give id, and every field again). A dollar-billed dentist pays in usd_amount only.')]
class SaveDentistPayment extends FormTool
{
    public function handle(Request $request, DentistPaymentController $controller): Response
    {
        $dentist = $this->find(Dentist::class, $request->get('dentist_id'), 'dentist_id');
        $date = $this->day($request->get('payment_date'), 'payment_date');

        $data = [
            'dentist_id' => $dentist->id,
            'payment_date' => $date,
            'notes' => $request->get('notes'),
            ...$this->money($request, $date, $dentist->isDollar()),
        ];

        $payment = $this->save(
            DentistPayment::class,
            $request->get('id'),
            fn () => $controller->store($this->form(StoreDentistPaymentRequest::class, $data)),
            fn (DentistPayment $payment) => $controller->update($this->form(UpdateDentistPaymentRequest::class, $data), $payment),
        );

        return Response::json(ListRecords::present($payment->load('dentist')));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The payment to correct. Omit to record a new one.'),
            'dentist_id' => $schema->integer()->required()->description('From list-dentists.'),
            'payment_date' => $schema->string()->description('YYYY-MM-DD. Defaults to today.'),
            ...$this->moneySchema($schema),
            'notes' => $schema->string(),
        ];
    }
}
