<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\MaterialPurchaseController;
use App\Http\Requests\StoreMaterialPurchaseRequest;
use App\Http\Requests\UpdateMaterialPurchaseRequest;
use App\Models\MaterialPurchase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record materials bought for the lab (omit id), or correct an existing purchase (give id, and every field again). The amount is the total paid, not a unit price.')]
class SaveMaterialPurchase extends FormTool
{
    public function handle(Request $request, MaterialPurchaseController $controller): Response
    {
        $date = $this->day($request->get('purchase_date'), 'purchase_date');
        $quantity = $request->get('quantity');

        $data = [
            'name' => $request->get('name'),
            'supplier' => $request->get('supplier'),
            // Free text in the app ("3 علب"), so a bare number is fine too.
            'quantity' => $quantity === null ? null : (string) $quantity,
            'purchase_date' => $date,
            'notes' => $request->get('notes'),
            ...$this->money($request, $date),
        ];

        $purchase = $this->save(
            MaterialPurchase::class,
            $request->get('id'),
            fn () => $controller->store($this->form(StoreMaterialPurchaseRequest::class, $data)),
            fn (MaterialPurchase $purchase) => $controller->update($this->form(UpdateMaterialPurchaseRequest::class, $data), $purchase),
        );

        return Response::json(ListRecords::present($purchase));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The purchase to correct. Omit to record a new one.'),
            'name' => $schema->string()->required()->description('The material bought.'),
            'supplier' => $schema->string(),
            'quantity' => $schema->string()->description('Free text, e.g. "3 علب" or "2 kg".'),
            'purchase_date' => $schema->string()->description('YYYY-MM-DD. Defaults to today.'),
            ...$this->moneySchema($schema),
            'notes' => $schema->string(),
        ];
    }
}
