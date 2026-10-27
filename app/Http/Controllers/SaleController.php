<?php

namespace App\Http\Controllers;

use App\Exceptions\LoyaltyChangedException;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\DayPass;
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
use App\Services\ManualDiscountService;
use App\Services\PromotionService;
use App\Services\SaleRevertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * @param  bool  $soloDisponibles  Rechazar productos, variantes o extras
     *                                 desactivados (false solo para el regalo de fidelidad, que lo
     *                                 agrega el sistema).
     */
    protected function buildItemsData(array $requestItems, bool $soloDisponibles = true): array
    {
        $subtotal = 0;
        $itemsData = [];
        $ingredientConsumption = [];

        foreach ($requestItems as $item) {
            $product = Product::findOrFail($item['product_id']);
            $unitPrice = (float) $product->base_price;
            $qty = (int) $item['qty'];

            // El servidor no confía en el carrito: el producto tiene que
            // seguir activo, y la variante y los extras tienen que ser de ESE
            // producto y estar activos (pasa si el admin los desactiva
            // mientras el cajero ya los tenía en el carrito).
            $noDisponible = fn (string $que) => throw ValidationException::withMessages([
                'items' => "{$que} de \"{$product->name}\" ya no está disponible. Quítalo del carrito y vuelve a agregarlo.",
            ]);

            if ($soloDisponibles && ! $product->is_active) {
                $noDisponible('El producto');
            }

            $variant = null;
            if (! empty($item['variant_id'])) {
                $variant = ProductVariant::where('product_id', $product->id)->find($item['variant_id']);
                if (! $variant || ($soloDisponibles && ! $variant->is_active)) {
                    $noDisponible('La opción elegida');
                }
                $unitPrice += (float) $variant->price_delta;
            }

            $modifiers = [];
            // Opciones repetidas ("Queso ×2"): el carrito manda el id una vez
            // por cada vez que se pidió. id => veces.
            $modifierCounts = array_count_values(array_map('intval', $item['modifier_ids'] ?? []));
            if (! empty($item['modifier_ids'])) {
                $modifierIds = array_keys($modifierCounts);
                $allowedGroupIds = $product->modifierGroups()
                    ->when($soloDisponibles, fn ($q) => $q->where('modifier_groups.is_active', true))
                    ->pluck('modifier_groups.id')
                    ->all();

                $modifiers = Modifier::with('group')->whereIn('id', $modifierIds)
                    ->whereIn('modifier_group_id', $allowedGroupIds)
                    ->when($soloDisponibles, fn ($q) => $q->where('is_active', true))
                    ->get();

                if ($modifiers->count() !== count($modifierIds)) {
                    $noDisponible('Un extra');
                }

                // La presentación (grupo obligatorio) es una sola: no se repite.
                foreach ($modifiers as $modifier) {
                    if ($modifierCounts[$modifier->id] > 1 && $modifier->group?->is_required) {
                        throw ValidationException::withMessages(['items' => "{$modifier->group->name} de \"{$product->name}\" no se puede repetir."]);
                    }
                }
            }

            // Obligatorios (la Presentación: Domo, Charola...) y máximos por
            // grupo: la pantalla ya no deja seguir sin ellos, pero el servidor
            // tampoco confía en eso.
            if ($soloDisponibles) {
                $elegidosPorGrupo = collect($modifiers)->countBy('modifier_group_id');

                foreach ($product->modifierGroups()->where('modifier_groups.is_active', true)->whereHas('modifiers', fn ($q) => $q->where('is_active', true))->get() as $group) {
                    $elegidos = $elegidosPorGrupo->get($group->id, 0);
                    $minimo = $group->is_required ? max(1, (int) $group->min_select) : (int) $group->min_select;

                    if ($elegidos < $minimo) {
                        throw ValidationException::withMessages(['items' => "Falta elegir {$group->name} de \"{$product->name}\"."]);
                    }

                    if ($group->max_select > 0 && $elegidos > $group->max_select) {
                        throw ValidationException::withMessages(['items' => "En {$group->name} de \"{$product->name}\" se pueden elegir máximo {$group->max_select}."]);
                    }
                }
            }

            // Las repeticiones no cuentan para el tope del grupo (arriba se
            // cuentan opciones distintas), pero sí se cobran cada una.
            foreach ($modifiers as $modifier) {
                $unitPrice += (float) $modifier->price_delta * $modifierCounts[$modifier->id];
            }

            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $itemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_id' => $variant?->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'modifiers' => $modifiers,
                'modifier_counts' => $modifierCounts,
                'notes' => isset($item['notes']) && trim($item['notes']) !== '' ? trim($item['notes']) : null,
                'manual_discount' => $item['discount'] ?? null,
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
                    // Queso ×2 en 3 papas = 6 porciones de queso.
                    $ingredientConsumption[$recipeItem->ingredient_id] =
                        ($ingredientConsumption[$recipeItem->ingredient_id] ?? 0) + ($recipeItem->qty * $qty * $modifierCounts[$modifier->id]);
                }
            }
        }

        return [$subtotal, $itemsData, $ingredientConsumption];
    }

    protected function itemRules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.modifier_ids' => 'array',
            'items.*.modifier_ids.*' => 'exists:modifiers,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:255',
            // Un cliente dado de baja no junta sellos (su tarjeta está inactiva).
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('is_active', true)],
            'day_pass_code' => 'nullable|string|max:20',
            ...ManualDiscountService::rules('items.*.discount'),
            ...ManualDiscountService::rules('sale_discount'),
        ];
    }

    /**
     * Suma los descuentos manuales del cajero encima de promociones y
     * fidelidad, y arma el desglose completo que se guarda con la venta.
     *
     * @return array{0: array, 1: float, 2: float, 3: array} [$itemsData, $discount, $manualDiscount, $breakdown]
     */
    protected function applyManualDiscounts(Request $request, array $itemsData, float $subtotal, float $autoDiscount, array $promoResult, ?array $loyaltyReward, ?array $dayPass = null): array
    {
        $breakdown = [];

        foreach ($promoResult['details'] as $promo) {
            $breakdown[] = ['kind' => 'promocion', 'label' => $promo['name'], 'product' => null, 'amount' => $promo['amount']];
        }

        if ($loyaltyReward && $loyaltyReward['discount_amount'] > 0) {
            $breakdown[] = [
                'kind' => 'fidelidad',
                'label' => $loyaltyReward['description'] ?? 'Premio de fidelidad',
                'product' => null,
                'amount' => round($loyaltyReward['discount_amount'], 2),
            ];
        }

        if ($dayPass && $dayPass['discount_amount'] > 0) {
            $breakdown[] = [
                'kind' => 'pase',
                'label' => "Pase del día {$dayPass['code']}: {$dayPass['label']}",
                'product' => null,
                'amount' => $dayPass['discount_amount'],
            ];
        }

        $manual = ManualDiscountService::apply($itemsData, $request->input('sale_discount'), $subtotal, $autoDiscount);
        $manualDiscount = round($manual['item_total'] + $manual['sale_amount'], 2);
        $discount = round(min($autoDiscount + $manualDiscount, $subtotal), 2);

        return [$manual['items'], $discount, $manualDiscount, [...$breakdown, ...$manual['breakdown']]];
    }

    /**
     * Calcula subtotal, descuento (promociones + fidelidad) y total SIN guardar
     * nada — para mostrarle al cajero el precio real antes de confirmar la venta.
     */
    public function preview(Request $request)
    {
        $request->validate($this->itemRules());

        [$subtotal, $itemsData] = $this->buildItemsData($request->items);

        $promoResult = (new PromotionService)->evaluate($itemsData);
        $discount = min($promoResult['discount'], $subtotal);

        $pass = $this->findDayPass($request);
        $redeemNow = $request->boolean('redeem_reward') && ! $pass;
        $reward = null;
        $autoAddedGift = null;
        $visitNote = null;

        if ($request->customer_id) {
            $customer = Customer::findOrFail($request->customer_id);
            $loyaltyService = new LoyaltyService;
            $loyaltyPreview = $loyaltyService->preview($customer, $subtotal - $discount, $itemsData);
            [$reward] = $this->resolveReward($loyaltyService, $customer, $loyaltyPreview, $subtotal - $discount, $itemsData);

            // Un sello al día: que el cajero sepa por qué no suma.
            if (! $loyaltyPreview['counts_visit']) {
                $visitNote = "{$customer->name} ya recibió su sello de hoy: esta compra no suma visita.";
            }

            // El pase no se junta con premios de la tarjeta: el premio queda para otra visita.
            if ($pass && $reward) {
                $visitNote = trim($visitNote.' Con pase del día no se cobra el premio de la tarjeta: queda pendiente.');
                $reward = null;
            }

            if ($redeemNow && $reward && $reward['type'] === 'gift' && ! $reward['free_product_in_cart']) {
                [$subtotal, $itemsData, , $reward, $autoAddedGift] = $this->applyGiftAutoAdd($reward, $subtotal, $itemsData, []);
            }

            if ($redeemNow && $reward && $reward['discount_amount'] > 0) {
                $discount = min($discount + $reward['discount_amount'], $subtotal);
            }
        }

        $passInfo = null;
        if ($pass) {
            [$subtotal, $itemsData, , $passInfo] = $this->applyDayPass($pass, $subtotal, $discount, $itemsData, []);
            $discount = min($discount + $passInfo['discount_amount'], $subtotal);
        }

        [, $discount, $manualDiscount, $breakdown] = $this->applyManualDiscounts(
            $request, $itemsData, $subtotal, $discount, $promoResult, $redeemNow ? $reward : null, $passInfo
        );

        $total = $subtotal - $discount;

        return response()->json([
            'day_pass' => $passInfo,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'manual_discount' => $manualDiscount,
            'discount_breakdown' => $breakdown,
            'total' => round($total, 2),
            'applied_promotions' => $promoResult['applied'],
            'loyalty' => $reward,
            'auto_added_gift' => $autoAddedGift,
            'visit_note' => $visitNote,
        ]);
    }

    /**
     * Cuando el cajero acepta un premio de "producto gratis" pero ese
     * producto todavía no está en el carrito, se agrega solo (1 pieza,
     * gratis) para que de verdad se refleje en el ticket - antes el
     * sistema solo le avisaba al cajero que lo agregara a mano, y si se le
     * olvidaba, el cliente se quedaba sin su premio sin que nadie se diera cuenta.
     *
     * @return array{0: float, 1: array, 2: array, 3: array, 4: ?array} [$subtotal, $itemsData, $ingredientConsumption, $reward, $autoAddedGift]
     */
    protected function applyGiftAutoAdd(array $reward, float $subtotal, array $itemsData, array $ingredientConsumption): array
    {
        $product = $reward['free_product_id'] ? Product::find($reward['free_product_id']) : null;

        if (! $product) {
            return [$subtotal, $itemsData, $ingredientConsumption, $reward, null];
        }

        [$giftSubtotal, $giftItemsData, $giftConsumption] = $this->buildItemsData([
            ['product_id' => $product->id, 'variant_id' => null, 'modifier_ids' => [], 'qty' => 1],
        ], soloDisponibles: false);

        foreach ($giftConsumption as $ingredientId => $qty) {
            $ingredientConsumption[$ingredientId] = ($ingredientConsumption[$ingredientId] ?? 0) + $qty;
        }

        $reward['discount_amount'] = round($giftSubtotal, 2);
        $reward['free_product_in_cart'] = true;

        return [
            $subtotal + $giftSubtotal,
            [...$itemsData, ...$giftItemsData],
            $ingredientConsumption,
            $reward,
            ['product_id' => $product->id, 'name' => $product->name, 'unit_price' => $giftSubtotal],
        ];
    }

    /**
     * El pase del día que escribió el cajero (null si no puso ninguno). Si
     * no existe, ya se usó o venció, se rechaza con el motivo.
     */
    protected function findDayPass(Request $request): ?DayPass
    {
        if (! $request->filled('day_pass_code')) {
            return null;
        }

        $pass = DayPass::with('product')->where('code', DayPass::normalize($request->day_pass_code))->first();

        $error = match (true) {
            ! $pass => 'Ese código de pase no existe. Revisa que esté bien escrito.',
            $pass->used_at !== null => 'Ese pase ya se usó'.($pass->usedSale ? " (venta #{$pass->usedSale->folio})" : '').'.',
            $pass->isExpired() => 'Ese pase venció el '.$pass->expires_on->locale('es')->translatedFormat('j \d\e F').'.',
            $pass->benefit_type === 'product' && ! $pass->product => 'El producto de ese pase ya no existe.',
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['day_pass_code' => $error]);
        }

        return $pass;
    }

    /**
     * Lo que descuenta el pase. Producto gratis: 1 pieza (si no está en el
     * carrito se agrega sola, como el regalo de fidelidad). %: sobre lo que
     * queda después de promociones. $: nunca más que la cuenta.
     *
     * @return array{0: float, 1: array, 2: array, 3: array} [$subtotal, $itemsData, $ingredientConsumption, $passInfo]
     */
    protected function applyDayPass(DayPass $pass, float $subtotal, float $discount, array $itemsData, array $ingredientConsumption): array
    {
        $base = max(0, $subtotal - $discount);
        $amount = 0.0;
        $autoAdded = null;

        if ($pass->benefit_type === 'product') {
            $inCart = collect($itemsData)->first(fn ($item) => (int) $item['product_id'] === (int) $pass->product_id);

            if ($inCart) {
                $amount = (float) $inCart['unit_price'];
            } else {
                [$extraSubtotal, $extraItems, $extraConsumption] = $this->buildItemsData([
                    ['product_id' => $pass->product_id, 'variant_id' => null, 'modifier_ids' => [], 'qty' => 1],
                ], soloDisponibles: false);

                foreach ($extraConsumption as $ingredientId => $qty) {
                    $ingredientConsumption[$ingredientId] = ($ingredientConsumption[$ingredientId] ?? 0) + $qty;
                }

                $subtotal += $extraSubtotal;
                $itemsData = [...$itemsData, ...$extraItems];
                $amount = $extraSubtotal;
                $autoAdded = ['product_id' => $pass->product_id, 'name' => $pass->product->name, 'unit_price' => $extraSubtotal];
            }
        } elseif ($pass->benefit_type === 'percent') {
            $amount = $base * min(100, (float) $pass->value) / 100;
        } else {
            $amount = min((float) $pass->value, $base);
        }

        return [$subtotal, $itemsData, $ingredientConsumption, [
            'id' => $pass->id,
            'code' => $pass->displayCode(),
            'label' => $pass->benefitLabel(),
            'note' => $pass->note,
            'discount_amount' => round($amount, 2),
            'auto_added' => $autoAdded,
        ]];
    }

    /**
     * Decide qué premio de fidelidad ofrecerle al cajero para esta venta:
     * prioriza uno que se gane justo en esta visita; si no hay, ofrece el
     * pendiente más viejo del cliente (de una visita anterior que no se
     * canjeó en el momento), si tiene uno sin vencer.
     *
     * @return array{0: ?array, 1: ?CustomerLoyaltyRedemption}
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
            ...$this->itemRules(),
            'payment_method' => 'required|in:'.implode(',', array_keys(Sale::PAYMENT_METHODS)),
            'redeem_reward' => 'nullable|boolean',
            'corrects_sale_id' => 'nullable|integer|exists:sales,id',
            'amount_received' => 'nullable|numeric|min:0|max:1000000',
        ]);

        $cashSession = CashSession::open();

        if (! $cashSession) {
            return response()->json(['success' => false, 'message' => 'No hay una caja abierta'], 422);
        }

        [$subtotal, $itemsData, $ingredientConsumption] = $this->buildItemsData($request->items);

        $promoResult = (new PromotionService)->evaluate($itemsData);
        $discount = min($promoResult['discount'], $subtotal);

        $customer = $request->customer_id ? Customer::findOrFail($request->customer_id) : null;
        $pass = $this->findDayPass($request);
        $redeemNow = $request->boolean('redeem_reward') && ! $pass; // el pase no se junta con premios
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

            if ($pass) {
                [$reward, $pendingRedemption] = [null, null];
            }

            if ($redeemNow && $reward && $reward['type'] === 'gift' && ! $reward['free_product_in_cart']) {
                [$subtotal, $itemsData, $ingredientConsumption, $reward] =
                    $this->applyGiftAutoAdd($reward, $subtotal, $itemsData, $ingredientConsumption);

                if ($loyaltyPreview['type'] === 'gift') {
                    $loyaltyPreview['discount_amount'] = $reward['discount_amount'];
                }
            }

            if ($redeemNow && $reward && $reward['discount_amount'] > 0) {
                $discount = min($discount + $reward['discount_amount'], $subtotal);
            }
        }

        $passInfo = null;
        if ($pass) {
            [$subtotal, $itemsData, $ingredientConsumption, $passInfo] = $this->applyDayPass($pass, $subtotal, $discount, $itemsData, $ingredientConsumption);
            $discount = min($discount + $passInfo['discount_amount'], $subtotal);
        }

        [$itemsData, $discount, $manualDiscount, $breakdown] = $this->applyManualDiscounts(
            $request, $itemsData, $subtotal, $discount, $promoResult, $redeemNow ? $reward : null, $passInfo
        );

        $total = $subtotal - $discount;

        foreach ($ingredientConsumption as $ingredientId => $neededQty) {
            $ingredient = Ingredient::find($ingredientId);

            if (! $ingredient || $ingredient->stock_qty < $neededQty) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay suficiente inventario de "'.($ingredient->name ?? 'un insumo').'" para completar esta venta.',
                ], 422);
            }
        }

        try {
            $sale = DB::transaction(function () use ($request, $cashSession, $subtotal, $discount, $manualDiscount, $breakdown, $total, $itemsData, $ingredientConsumption, $customer, $loyaltyPreview, $loyaltyService, $reward, $pendingRedemption, $redeemNow, $pass) {
                // Un solo uso: con candado, por si otra tablet lo está cobrando ahorita.
                if ($pass && ! DayPass::whereKey($pass->id)->whereNull('used_at')->lockForUpdate()->exists()) {
                    throw new LoyaltyChangedException('Ese pase se acaba de usar en otra venta.');
                }

                $sale = Sale::create([
                    'folio' => 'TMP',
                    'user_id' => auth()->id(),
                    'cash_session_id' => $cashSession->id,
                    'customer_id' => $request->customer_id,
                    'day_pass_id' => $pass?->id,
                    'status' => 'pagada',
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'manual_discount' => $manualDiscount,
                    'discount_breakdown' => $breakdown ?: null,
                    'total' => $total,
                    'payment_method' => $request->payment_method,
                    // Efectivo: con cuánto pagó (nunca menos que el total; si no
                    // llegó el dato o es menor, se cobró exacto).
                    'amount_received' => $request->payment_method === 'efectivo'
                        ? max((float) $total, (float) ($request->amount_received ?? 0))
                        : null,
                ]);

                $sale->update(['folio' => str_pad($sale->id, 4, '0', STR_PAD_LEFT)]);
                $pass?->update(['used_at' => now(), 'used_sale_id' => $sale->id]);

                foreach ($itemsData as $data) {
                    $saleItem = SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $data['product_id'],
                        'variant_id' => $data['variant_id'],
                        'qty' => $data['qty'],
                        'unit_price' => $data['unit_price'],
                        'line_total' => $data['line_total'],
                        'notes' => $data['notes'] ?? null,
                        'manual_discount' => $data['manual_discount_amount'] ?? 0,
                        'manual_discount_type' => ($data['manual_discount_amount'] ?? 0) > 0 ? $data['manual_discount']['type'] : null,
                        'manual_discount_value' => ($data['manual_discount_amount'] ?? 0) > 0 ? ($data['manual_discount']['value'] ?? null) : null,
                        'manual_discount_reason' => ($data['manual_discount_amount'] ?? 0) > 0 ? $data['manual_discount']['reason'] : null,
                    ]);

                    foreach ($data['modifiers'] as $modifier) {
                        SaleItemModifier::create([
                            'sale_item_id' => $saleItem->id,
                            'modifier_id' => $modifier->id,
                            'qty' => $data['modifier_counts'][$modifier->id] ?? 1,
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
                    // Candado sobre el cliente: si otra tablet le cobró en este
                    // mismo momento, el sello y el premio se calcularon con datos
                    // viejos (se perdería un sello o se daría el premio dos
                    // veces). Se cancela esta venta para que se vuelva a cobrar.
                    $locked = Customer::whereKey($customer->id)->lockForUpdate()->first();

                    // ...o si en ese momento otra tablet le dio su sello de hoy.
                    if (! $locked || (int) $locked->current_visits !== (int) $customer->current_visits
                        || (int) $locked->current_level !== (int) $customer->current_level
                        || (($loyaltyPreview['counts_visit'] ?? true) && $loyaltyService->visitedToday($locked))) {
                        throw new LoyaltyChangedException('El cliente acaba de registrar otra visita. Vuelve a tocar Cobrar.');
                    }

                    if ($pendingRedemption && $redeemNow) {
                        $stillPending = CustomerLoyaltyRedemption::whereKey($pendingRedemption->id)
                            ->where('status', 'pending')->lockForUpdate()->exists();

                        if (! $stillPending) {
                            throw new LoyaltyChangedException('Ese premio ya se cobró en otra venta. Vuelve a tocar Cobrar.');
                        }
                    }

                    $customer = $locked;

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
        } catch (LoyaltyChangedException $e) {
            // Nada se guardó (la transacción se deshizo completa).
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        // La orden venía de "Deshacer" (para corregirla): la original queda
        // como "Corregida → #nueva" y la comanda sale marcada CORRECCIÓN.
        if ($request->filled('corrects_sale_id')) {
            $original = Sale::find($request->corrects_sale_id);

            if ($original && $original->revert_type === 'deshecha' && ! $original->replaced_by_sale_id
                && $original->cash_session_id === $sale->cash_session_id) {
                app(SaleRevertService::class)->linkCorrection($original, $sale);
            }
        }

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'folio' => $sale->folio,
            'total' => (float) $sale->total,
            'loyalty' => $reward,
        ]);
    }
}
