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
    public const PENDING_REWARD_DAYS = 7;

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
            $result = [...$result, ...$this->computeReward($level, 'discount', $baseAmount, $itemsData)];
        } elseif ($nextVisit === self::VISITS_FOR_GIFT) {
            $result = [...$result, ...$this->computeReward($level, 'gift', $baseAmount, $itemsData)];
        }

        return $result;
    }

    /**
     * A diferencia de preview() (que solo clasifica si la SIGUIENTE visita exacta
     * es premio), esto calcula cuál es el próximo premio en general y cuántas
     * visitas faltan para llegar, sin importar en qué visita vaya el cliente.
     *
     * @return array{label: string, visits_remaining: int}
     */
    public function nextRewardSummary(Customer $customer): array
    {
        $level = $customer->currentLoyaltyLevel();
        $visits = $customer->current_visits;

        if ($visits < self::VISITS_FOR_DISCOUNT) {
            $target = self::VISITS_FOR_DISCOUNT;
            $label = $level
                ? ($level->discount_description ?: $level->discount_percent . '% de descuento')
                : 'Descuento';
        } else {
            $target = self::VISITS_FOR_GIFT;
            $label = $level
                ? ($level->gift_description ?: 'Producto gratis')
                : 'Producto gratis';
        }

        return [
            'label' => $label,
            'visits_remaining' => max(0, $target - $visits),
        ];
    }

    /**
     * El canje pendiente (ganado pero aún no reclamado, sin vencer) más
     * antiguo de este cliente, si tiene alguno.
     */
    public function pendingRewardFor(Customer $customer): ?CustomerLoyaltyRedemption
    {
        return CustomerLoyaltyRedemption::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', Carbon::now())
            ->oldest('earned_at')
            ->first();
    }

    /**
     * Calcula cuánto valdría, contra el carrito ACTUAL, un canje pendiente
     * que se ganó en una visita anterior (con un carrito distinto).
     */
    public function rewardAmountFor(CustomerLoyaltyRedemption $redemption, float $baseAmount, array $itemsData): array
    {
        $level = $redemption->loyaltyLevel;

        if (! $level) {
            return ['discount_amount' => 0, 'description' => null, 'free_product_name' => null, 'free_product_in_cart' => false];
        }

        return $this->computeReward($level, $redemption->type, $baseAmount, $itemsData);
    }

    /**
     * @return array{type: string, discount_amount: float, description: string, free_product_name: ?string, free_product_in_cart: bool}
     */
    protected function computeReward(LoyaltyLevel $level, string $type, float $baseAmount, array $itemsData): array
    {
        if ($type === 'discount') {
            return [
                'type' => 'discount',
                'discount_amount' => round($baseAmount * ((float) $level->discount_percent / 100), 2),
                'description' => $level->discount_description ?: $level->discount_percent . '% de descuento',
                'free_product_name' => null,
                'free_product_in_cart' => false,
            ];
        }

        $result = [
            'type' => 'gift',
            'discount_amount' => 0,
            'description' => $level->gift_description ?: 'Producto gratis',
            'free_product_name' => $level->freeProduct?->name,
            'free_product_in_cart' => false,
        ];

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

        return $result;
    }

    /**
     * Guarda el resultado de una visita ya calculada con preview(): el
     * avance de visitas/nivel del cliente SIEMPRE ocurre, sin importar si
     * el premio se canjea en el momento o no. Si esta visita ganó un
     * premio nuevo, se registra: canjeado de inmediato si $redeemNow,
     * o pendiente (con una semana para reclamarlo) si no.
     */
    public function applyVisit(Customer $customer, Sale $sale, array $preview, bool $redeemNow): void
    {
        if ($preview['type'] && $preview['level_id']) {
            $now = Carbon::now();

            CustomerLoyaltyRedemption::create([
                'customer_id' => $customer->id,
                'loyalty_level_id' => $preview['level_id'],
                'type' => $preview['type'],
                'status' => $redeemNow ? 'redeemed' : 'pending',
                'earned_at' => $now,
                'expires_at' => $now->copy()->addDays(self::PENDING_REWARD_DAYS),
                'redeemed_at' => $redeemNow ? $now : null,
                'sale_id' => $sale->id,
                'redeemed_sale_id' => $redeemNow ? $sale->id : null,
                'discount_applied' => $redeemNow && $preview['discount_amount'] > 0 ? $preview['discount_amount'] : null,
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

    /**
     * Marca como canjeado un premio que se había quedado pendiente de una
     * visita anterior, aplicado ahora en una venta distinta.
     */
    public function redeemPending(CustomerLoyaltyRedemption $redemption, Sale $sale, float $discountAmount): void
    {
        $redemption->update([
            'status' => 'redeemed',
            'redeemed_at' => Carbon::now(),
            'redeemed_sale_id' => $sale->id,
            'discount_applied' => $discountAmount > 0 ? $discountAmount : null,
        ]);
    }
}
