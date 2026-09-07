<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemModifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.modifier_ids' => 'array',
            'items.*.modifier_ids.*' => 'exists:modifiers,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $cashSession = CashSession::open();

        if (! $cashSession) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        $sale = DB::transaction(function () use ($request, $cashSession) {
            $subtotal = 0;
            $itemsData = [];

            // Recalculamos los precios en el servidor (nunca confiamos ciegamente en lo que manda el navegador)
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $unitPrice = (float) $product->base_price;

                $variant = null;
                if (! empty($item['variant_id'])) {
                    $variant = ProductVariant::findOrFail($item['variant_id']);
                    $unitPrice += (float) $variant->price_delta;
                }

                $modifiers = [];
                if (! empty($item['modifier_ids'])) {
                    $modifiers = Modifier::whereIn('id', $item['modifier_ids'])->get();
                    foreach ($modifiers as $modifier) {
                        $unitPrice += (float) $modifier->price_delta;
                    }
                }

                $qty = (int) $item['qty'];
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'modifiers' => $modifiers,
                ];
            }

            $total = $subtotal; // sin descuento por ahora

            $sale = Sale::create([
                'folio' => 'TMP',
                'user_id' => auth()->id(),
                'cash_session_id' => $cashSession->id,
                'status' => 'pagada',
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $total,
                'payment_method' => 'efectivo', // se ajusta el 14 sep con la pantalla de cobro real
            ]);

            $sale->update(['folio' => str_pad($sale->id, 4, '0', STR_PAD_LEFT)]);

            foreach ($itemsData as $data) {
                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $data['product_id'],
                    'variant_id' => $data['variant_id'],
                    'qty' => $data['qty'],
                    'unit_price' => $data['unit_price'],
                    'line_total' => $data['line_total'],
                ]);

                foreach ($data['modifiers'] as $modifier) {
                    SaleItemModifier::create([
                        'sale_item_id' => $saleItem->id,
                        'modifier_id' => $modifier->id,
                        'price_delta' => $modifier->price_delta,
                    ]);
                }
            }

            return $sale;
        });

        return response()->json([
            'success' => true,
            'folio' => $sale->folio,
            'total' => (float) $sale->total,
        ]);
    }
}