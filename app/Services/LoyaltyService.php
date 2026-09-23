<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyLevel;
use App\Models\Sale;
use Carbon\Carbon;

class LoyaltyService
{
    public const VISITS_FOR_DISCOUNT = 4;
    public const VISITS_FOR_GIFT = 8;

    /**
     * Calcula qué pasaría con la SIGUIENTE visita de este cliente, sin guardar nada.
     *
     * @param  array  $itemsData  El carrito actual (para checar si el producto de regalo ya está incluido).
     */
    public function preview(Customer $customer, float $baseAmount = 0, array $itemsData = []): array
    {
        $nextVisit = $customer->current_visits + 1;
        $level = $customer->currentLoyaltyLevel();

        $result = [
            'next_visit' => $nextVisit,
            'type' => null,
            'discount_amount' => 0,
            'description' => null,
            'free_product_name' => null,
            'free_product_in_cart' => false,
            'level_id' => $level?->id,
        ];

        if (! $level) {
            return $result;
        }

        if ($nextVisit === self::VISITS_FOR_DISCOUNT) {
            $result['type'] = 'discount';
            $result['discount_amount'] = round($baseAmount * ((float) $level->discount_percent / 100), 2);
            $result['description'] = $level->discount_description ?: $level->discount_percent . '% de descuento';
        } elseif ($nextVisit === self::VISITS_FOR_GIFT) {
            $result['type'] = 'gift';
            $result['free_product_name'] = $level->freeProduct?->name;
            $result['description'] = $level->gift_description ?: 'Producto gratis';

            if ($level->free_product_id) {
                foreach ($itemsData as $item) {
                    if ((int) $item['product_id'] === (int) $level->free_product_id) {
                        // Se regala solo 1 unidad, aunque el carrito tenga más.
                        $result['discount_amount'] = round((float) $item['unit_price'], 2);
                        $result['free_product_in_cart'] = true;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Guarda el resultado de una visita ya calculada con preview(): registra el
     * canje (si aplica) y avanza al cliente (suma visita, o resetea y sube de
     * nivel si llegó a la visita 8).
     */
    public function applyVisit(Customer $customer, Sale $sale, array $preview): void
    {
        if ($preview['type'] && $preview['level_id']) {
            CustomerLoyaltyRedemption::create([
                'customer_id' => $customer->id,
                'loyalty_level_id' => $preview['level_id'],
                'type' => $preview['type'],
                'redeemed_at' => Carbon::now(),
                'sale_id' => $sale->id,
                'discount_applied' => $preview['discount_amount'] > 0 ? $preview['discount_amount'] : null,
            ]);
        }

        if ($preview['next_visit'] >= self::VISITS_FOR_GIFT) {
            $nextLevel = LoyaltyLevel::forLevelNumber($customer->current_level + 1);

            $customer->update([
                'current_level' => $nextLevel ? $nextLevel->level_number : $customer->current_level,
                'current_visits' => 0,
            ]);
        } else {
            $customer->update(['current_visits' => $preview['next_visit']]);
        }
    }
}