<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Modifier;
use App\Models\ModifierRecipeItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemModifier;
use App\Services\LoyaltyService;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    protected function buildItemsData(array $requestItems): array
    {
        $subtotal = 0;
        $itemsData = [];
        $ingredientConsumption = [];

        foreach ($requestItems as $item) {
            $product = Product::findOrFail($item['product_id']);
            $unitPrice = (float) $product->base_price;
            $qty = (int) $item['qty'];

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

            $baseRecipe = RecipeItem::where('product_id', $product->id)->whereNull('variant_id')->get();
            foreach ($baseRecipe as $recipeItem) {
                $ingredientConsumption[$recipeItem->ingredient_id] =
                    ($ingredientConsumption[$recipeItem->ingredient_id] ?? 0) + ($recipeItem->qty * $qty);
            }

            if ($variant) {
                $variantRecipe = RecipeItem::where('product_id', $product->id)->where('variant_id', $variant->id)->get();
                foreach ($variantRecipe as $recipeItem) {
                    $ingredientConsumption[$recipeItem->ingredient_id] =
                        ($ingredientConsumption[$recipeItem->ingredient_id] ?? 0) + ($recipeItem->qty * $qty);
                }
            }

            foreach ($modifiers as $modifier) {
                $modifierRecipe = ModifierRecipeItem::where('modifier_id', $modifier->id)->get();
                foreach ($modifierRecipe as $recipeItem) {
                    $ingredientConsumption[$recipeItem->ingredient_id] =
                        ($ingredientConsumption[$recipeItem->ingredient_id] ?? 0) + ($recipeItem->qty * $qty);
                }
            }
        }

        return [$subtotal, $itemsData, $ingredientConsumption];
    }

    /**
     * Calcula subtotal, descuento (promociones + fidelidad) y total SIN guardar
     * nada — para mostrarle al cajero el precio real antes de confirmar la venta.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.modifier_ids' => 'array',
            'items.*.qty' => 'required|integer|min:1',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        [$subtotal, $itemsData] = $this->buildItemsData($request->items);

        $promoResult = (new PromotionService)->evaluate($itemsData);
        $discount = min($promoResult['discount'], $subtotal);

        $redeemNow = $request->boolean('redeem_reward');
        $reward = null;

        if ($request->customer_id) {
            $customer = Customer::findOrFail($request->customer_id);
            $loyaltyService = new LoyaltyService;
            $loyaltyPreview = $loyaltyService->preview($customer, $subtotal - $discount, $itemsData);
            [$reward] = $this->resolveReward($loyaltyService, $customer, $loyaltyPreview, $subtotal - $discount, $itemsData);

            if ($redeemNow && $reward && $reward['discount_amount'] > 0) {
                $discount = min($discount + $reward['discount_amount'], $subtotal);
            }
        }

        $total = $subtotal - $discount;

        return response()->json([
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'applied_promotions' => $promoResult['applied'],
            'loyalty' => $reward,
        ]);
    }

    /**
     * Decide qué premio de fidelidad ofrecerle al cajero para esta venta:
     * prioriza uno que se gane justo en esta visita; si no hay, ofrece el
     * pendiente más viejo del cliente (de una visita anterior que no se
     * canjeó en el momento), si tiene uno sin vencer.
     *
     * @return array{0: ?array, 1: ?\App\Models\CustomerLoyaltyRedemption}
     */
    protected function resolveReward(LoyaltyService $loyaltyService, Customer $customer, array $loyaltyPreview, float $baseAmount, array $itemsData): array
    {
        if ($loyaltyPreview['type']) {
            return [[...$loyaltyPreview, 'source' => 'new'], null];
        }

        $pending = $loyaltyService->pendingRewardFor($customer);

        if (! $pending) {
            return [null, null];
        }

        $reward = $loyaltyService->rewardAmountFor($pending, $baseAmount, $itemsData);

        return [[...$reward, 'source' => 'pending', 'expires_at' => $pending->expires_at?->toIso8601String()], $pending];
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.modifier_ids' => 'array',
            'items.*.modifier_ids.*' => 'exists:modifiers,id',
            'items.*.qty' => 'required|integer|min:1',
            'payment_method' => 'required|in:efectivo,tarjeta',
            'customer_id' => 'nullable|exists:customers,id',
            'redeem_reward' => 'nullable|boolean',
        ]);

        $cashSession = CashSession::open();

        if (! $cashSession) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        [$subtotal, $itemsData, $ingredientConsumption] = $this->buildItemsData($request->items);

        $promoResult = (new PromotionService)->evaluate($itemsData);
        $discount = min($promoResult['discount'], $subtotal);

        $customer = $request->customer_id ? Customer::findOrFail($request->customer_id) : null;
        $redeemNow = $request->boolean('redeem_reward');
        $loyaltyService = new LoyaltyService;
        $reward = null;
        $pendingRedemption = null;
        $loyaltyPreview = null;

        if ($customer) {
            // preview() se recalcula aquí (independiente del de arriba en
            // preview()) para no confiar en nada que haya mandado el
            // cliente - el servidor es quien decide qué premio aplica.
            $loyaltyPreview = $loyaltyService->preview($customer, $subtotal - $discount, $itemsData);
            [$reward, $pendingRedemption] = $this->resolveReward($loyaltyService, $customer, $loyaltyPreview, $subtotal - $discount, $itemsData);

            if ($redeemNow && $reward && $reward['discount_amount'] > 0) {
                $discount = min($discount + $reward['discount_amount'], $subtotal);
            }
        }
        $total = $subtotal - $discount;

        foreach ($ingredientConsumption as $ingredientId => $neededQty) {
            $ingredient = Ingredient::find($ingredientId);

            if (! $ingredient || $ingredient->stock_qty < $neededQty) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay suficiente inventario de "' . ($ingredient->name ?? 'un insumo') . '" para completar esta venta.',
                ], 422);
            }
        }

        $sale = DB::transaction(function () use ($request, $cashSession, $subtotal, $discount, $total, $itemsData, $ingredientConsumption, $customer, $loyaltyPreview, $loyaltyService, $reward, $pendingRedemption, $redeemNow) {
            $sale = Sale::create([
                'folio' => 'TMP',
                'user_id' => auth()->id(),
                'cash_session_id' => $cashSession->id,
                'customer_id' => $request->customer_id,
                'status' => 'pagada',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $request->payment_method,
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

            foreach ($ingredientConsumption as $ingredientId => $consumedQty) {
                $ingredient = Ingredient::find($ingredientId);

                InventoryMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => 'venta',
                    'qty' => -$consumedQty,
                    'unit_cost' => $ingredient->cost_per_unit,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'user_id' => auth()->id(),
                ]);

                $ingredient->decrement('stock_qty', $consumedQty);
            }

            if ($customer && $loyaltyPreview) {
                // Avanza visitas/nivel siempre - el canje (si esta visita
                // ganó uno nuevo) queda pendiente o inmediato según
                // $redeemNow. Si el premio que se canjeó era uno viejo
                // pendiente (no de esta visita), se resuelve aparte abajo.
                $loyaltyService->applyVisit($customer, $sale, $loyaltyPreview, $redeemNow);

                if ($pendingRedemption && $redeemNow) {
                    $loyaltyService->redeemPending($pendingRedemption, $sale, $reward['discount_amount']);
                }
            }

            return $sale;
        });

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'folio' => $sale->folio,
            'total' => (float) $sale->total,
            'loyalty' => $reward,
        ]);
    }
}