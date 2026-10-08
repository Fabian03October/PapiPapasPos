<?php

namespace App\Services;

/**
 * Descuentos y cortesías que el cajero pone a mano en el carrito - a un
 * producto o a toda la venta. Se aplican DESPUÉS de las promociones
 * automáticas y la fidelidad (nunca las pisan), y cada uno lleva su motivo
 * para que se pueda auditar en el historial de ventas.
 *
 * Un descuento viene como ['type' => percent|amount|courtesy, 'value' => ?, 'reason' => '...'].
 */
class ManualDiscountService
{
    public const TYPES = ['percent', 'amount', 'courtesy'];

    /**
     * Reglas de validación para un descuento manual bajo $prefix
     * (ej. "items.*.discount" o "sale_discount").
     */
    public static function rules(string $prefix): array
    {
        return [
            $prefix => 'nullable|array',
            "{$prefix}.type" => 'required_with:' . $prefix . '|in:' . implode(',', self::TYPES),
            "{$prefix}.value" => 'nullable|required_if:' . $prefix . '.type,percent,amount|numeric|gt:0',
            "{$prefix}.reason" => 'required_with:' . $prefix . '|string|min:3|max:255',
        ];
    }

    /**
     * Cuánto se descuenta de $base con este descuento (nunca más que $base).
     */
    public static function amountFor(?array $discount, float $base): float
    {
        if (! $discount || $base <= 0) {
            return 0;
        }

        $amount = match ($discount['type']) {
            'courtesy' => $base,
            'percent' => $base * min(100, (float) $discount['value']) / 100,
            'amount' => min((float) $discount['value'], $base),
            default => 0,
        };

        return round(min($amount, $base), 2);
    }

    public static function label(?string $type, $value, ?string $reason): string
    {
        $label = match ($type) {
            'courtesy' => 'Cortesía',
            'percent' => 'Desc. ' . rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . '%',
            default => 'Desc. $' . number_format((float) $value, 2),
        };

        return $reason ? "{$label} ({$reason})" : $label;
    }

    /**
     * Aplica los descuentos manuales sobre lo que queda después de las
     * promociones/fidelidad ($autoDiscount).
     *
     * @param  array  $itemsData  los items de SaleController::buildItemsData(), cada uno con 'manual_discount' (array|null)
     * @return array{items: array, item_total: float, sale_amount: float, breakdown: array}
     *               items: $itemsData con 'manual_discount_amount' calculado por línea
     */
    public static function apply(array $itemsData, ?array $saleDiscount, float $subtotal, float $autoDiscount): array
    {
        $rawTotal = 0;

        foreach ($itemsData as $i => $item) {
            $itemsData[$i]['manual_discount_amount'] = self::amountFor($item['manual_discount'] ?? null, (float) $item['line_total']);
            $rawTotal += $itemsData[$i]['manual_discount_amount'];
        }

        // Si las promociones ya se comieron parte de esas líneas, el descuento
        // manual no puede dejar la venta en negativo: se recorta parejo en
        // cada línea para que el desglose siga sumando exacto.
        $available = max(0, $subtotal - $autoDiscount);
        $scale = $rawTotal > $available ? $available / $rawTotal : 1;

        $itemTotal = 0;
        $breakdown = [];

        foreach ($itemsData as $i => $item) {
            $amount = round($item['manual_discount_amount'] * $scale, 2);
            $itemsData[$i]['manual_discount_amount'] = $amount;

            if ($amount > 0) {
                $itemTotal += $amount;
                $breakdown[] = [
                    'kind' => 'manual_producto',
                    'label' => self::label($item['manual_discount']['type'], $item['manual_discount']['value'] ?? null, $item['manual_discount']['reason']),
                    'product' => $item['product_name'] ?? null,
                    'amount' => $amount,
                ];
            }
        }

        $itemTotal = round(min($itemTotal, $available), 2);

        $remaining = max(0, $subtotal - $autoDiscount - $itemTotal);
        $saleAmount = self::amountFor($saleDiscount, $remaining);

        if ($saleAmount > 0) {
            $breakdown[] = [
                'kind' => 'manual_venta',
                'label' => self::label($saleDiscount['type'], $saleDiscount['value'] ?? null, $saleDiscount['reason']),
                'product' => null,
                'amount' => $saleAmount,
            ];
        }

        return [
            'items' => $itemsData,
            'item_total' => $itemTotal,
            'sale_amount' => $saleAmount,
            'breakdown' => $breakdown,
        ];
    }
}
