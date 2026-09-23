<?php

namespace App\Services;

use App\Models\Promotion;
use Carbon\Carbon;

class PromotionService
{
    /**
     * Evalúa todas las promociones activas contra el carrito y devuelve
     * el descuento total a aplicar (sumando todas las que apliquen).
     *
     * @param  array  $itemsData  Cada item: ['product_id', 'variant_id', 'qty', 'unit_price', 'line_total', 'modifiers']
     * @return array  ['discount' => float, 'applied' => array de nombres de promociones aplicadas]
     */
    public function evaluate(array $itemsData): array
    {
        $now = Carbon::now();
        $todayDow = (int) $now->format('w'); // 0=domingo ... 6=sábado, igual que guardamos en days_of_week

        $activePromotions = Promotion::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $now);
            })
            ->with(['products', 'modifiers'])
            ->get()
            ->filter(function (Promotion $promo) use ($todayDow, $now) {
                if (! empty($promo->days_of_week) && ! in_array($todayDow, $promo->days_of_week)) {
                    return false;
                }
                if ($promo->start_time && $now->format('H:i:s') < $promo->start_time) {
                    return false;
                }
                if ($promo->end_time && $now->format('H:i:s') > $promo->end_time) {
                    return false;
                }

                return true;
            });

        $totalDiscount = 0;
        $appliedNames = [];

        foreach ($activePromotions as $promo) {
            if ($promo->type === 'percent_off_sale') {
                $discount = $this->evaluatePercentOff($promo, $itemsData);
            } else {
                $discount = $this->evaluateFixedPrice($promo, $itemsData);
            }

            if ($discount > 0) {
                $totalDiscount += $discount;
                $appliedNames[] = $promo->name;
            }
        }

        return [
            'discount' => round($totalDiscount, 2),
            'applied' => $appliedNames,
        ];
    }

    protected function evaluatePercentOff(Promotion $promo, array $itemsData): float
    {
        $hasProductScope = $promo->products->isNotEmpty();
        $hasModifierScope = $promo->modifiers->isNotEmpty();

        // Sin productos ni extras específicos: aplica a toda la venta
        if (! $hasProductScope && ! $hasModifierScope) {
            $scopeSubtotal = collect($itemsData)->sum('line_total');

            return $scopeSubtotal * ((float) $promo->percent_value / 100);
        }

        $productIds = $promo->products->pluck('id')->all();
        $modifierIds = $promo->modifiers->pluck('id')->all();
        $scopeSubtotal = 0;

        foreach ($itemsData as $item) {
            $matchesProduct = ! $hasProductScope || in_array($item['product_id'], $productIds);

            if (! $matchesProduct) {
                continue;
            }

            if ($hasModifierScope) {
                // Descuento sobre el costo del extra dentro de esta línea, no sobre el producto completo
                foreach ($item['modifiers'] as $modifier) {
                    if (in_array($modifier->id, $modifierIds)) {
                        $scopeSubtotal += (float) $modifier->price_delta * $item['qty'];
                    }
                }
            } else {
                $scopeSubtotal += $item['line_total'];
            }
        }

        return $scopeSubtotal * ((float) $promo->percent_value / 100);
    }

    protected function evaluateFixedPrice(Promotion $promo, array $itemsData): float
    {
        if ($promo->products->isEmpty()) {
            return 0;
        }

        // Cuánto de cada producto hay disponible en el carrito
        $availableQty = [];
        foreach ($itemsData as $item) {
            $availableQty[$item['product_id']] = ($availableQty[$item['product_id']] ?? 0) + $item['qty'];
        }

        // Cuántas veces se puede formar el combo/precio-especial completo con lo que hay en el carrito
        $maxInstances = null;
        $originalPriceSum = 0;

        foreach ($promo->products as $product) {
            $required = $product->pivot->qty_required;
            $have = $availableQty[$product->id] ?? 0;
            $possible = intdiv($have, $required);

            $maxInstances = $maxInstances === null ? $possible : min($maxInstances, $possible);
            $originalPriceSum += $required * (float) $product->base_price;
        }

        if (! $maxInstances || $maxInstances <= 0) {
            return 0;
        }

        $originalTotal = $originalPriceSum * $maxInstances;
        $fixedTotal = (float) $promo->combo_price * $maxInstances;

        return max(0, $originalTotal - $fixedTotal);
    }
}