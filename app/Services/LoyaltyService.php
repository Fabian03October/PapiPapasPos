<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyAdjustment;
use App\Models\LoyaltyLevel;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        // Un sello al día: si ya lo recibió hoy, esta compra no suma visita ni
        // gana premio nuevo (un premio pendiente de antes sí se puede cobrar).
        if ($this->visitedToday($customer)) {
            return [
                'next_visit' => (int) $customer->current_visits,
                'counts_visit' => false,
                'type' => null,
                'discount_amount' => 0,
                'description' => null,
                'free_product_id' => null,
                'free_product_name' => null,
                'free_product_in_cart' => false,
                'level_id' => $customer->currentLoyaltyLevel()?->id,
            ];
        }

        $nextVisit = $customer->current_visits + 1;
        $level = $customer->currentLoyaltyLevel();

        $result = [
            'next_visit' => $nextVisit,
            'counts_visit' => true,
            'type' => null,
            'discount_amount' => 0,
            'description' => null,
            'free_product_id' => null,
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
                ? ($level->discount_description ?: $level->discount_percent.'% de descuento')
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
     * Ajuste a mano de los sellos de un cliente (ej. pasar su tarjeta física
     * a la digital). Queda en la bitácora con quién, cuándo, de cuánto a
     * cuánto y por qué. Opcional: darle un premio por cobrar (descuento o
     * regalo de su nivel), por si ya lo había ganado en la tarjeta física.
     */
    public function adjust(Customer $customer, User $by, int $level, int $visits, int $cards, string $reason, ?string $reward = null): LoyaltyAdjustment
    {
        $loyaltyLevel = LoyaltyLevel::forLevelNumber($level);

        $errors = [];
        if (! $loyaltyLevel) {
            $errors['level'] = 'Ese nivel no existe.';
        }
        if ($visits < 0 || $visits >= self::VISITS_FOR_GIFT) {
            $errors['visits'] = 'Los sellos van de 0 a '.(self::VISITS_FOR_GIFT - 1).'.';
        }
        if ($loyaltyLevel && ($cards < 0 || $cards >= $loyaltyLevel->timesToAdvance())) {
            $errors['cards'] = 'En ese nivel las tarjetas llenas van de 0 a '.($loyaltyLevel->timesToAdvance() - 1).'.';
        }
        if (trim($reason) === '') {
            $errors['reason'] = 'Escribe el motivo del ajuste.';
        }
        if ($reward !== null && ! in_array($reward, ['discount', 'gift'], true)) {
            $errors['reward'] = 'Premio no válido.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($customer, $by, $loyaltyLevel, $visits, $cards, $reason, $reward) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $before = ['level' => (int) $customer->current_level, 'visits' => (int) $customer->current_visits, 'cards' => (int) $customer->cards_completed];

            $customer->update([
                'current_level' => $loyaltyLevel->level_number,
                'current_visits' => $visits,
                'cards_completed' => $cards,
            ]);

            if ($reward) {
                CustomerLoyaltyRedemption::create([
                    'customer_id' => $customer->id,
                    'loyalty_level_id' => $loyaltyLevel->id,
                    'type' => $reward,
                    'status' => 'pending',
                    'earned_at' => now(),
                    'expires_at' => now()->addDays(self::PENDING_REWARD_DAYS),
                ]);
            }

            return LoyaltyAdjustment::create([
                'customer_id' => $customer->id,
                'user_id' => $by->id,
                'before' => $before,
                'after' => ['level' => $loyaltyLevel->level_number, 'visits' => $visits, 'cards' => $cards],
                'reward_given' => $reward,
                'reason' => trim($reason),
            ]);
        });
    }

    /**
     * Tarjeta llena (visita 8): se repite el nivel las veces que diga ese
     * nivel ("veces que se llena la tarjeta para subir"); al cumplirlas sube
     * al siguiente, y después del último regresa al primero (ciclo).
     *
     * @return array{current_level: int, current_visits: int, cards_completed: int}
     */
    public function afterFullCard(Customer $customer): array
    {
        $level = $customer->currentLoyaltyLevel();
        $cards = (int) $customer->cards_completed + 1;

        if ($level && $cards < $level->timesToAdvance()) {
            return ['current_level' => (int) $customer->current_level, 'current_visits' => 0, 'cards_completed' => $cards];
        }

        $next = LoyaltyLevel::where('level_number', '>', (int) $customer->current_level)->orderBy('level_number')->first()
            ?? LoyaltyLevel::firstLevel();

        return [
            'current_level' => $next?->level_number ?? (int) $customer->current_level,
            'current_visits' => 0,
            'cards_completed' => 0,
        ];
    }

    /**
     * ¿Ya recibió su sello hoy? (día de calendario, hora del negocio). Las
     * ventas deshechas no cuentan (su sello se regresó); las canceladas sí
     * (cancelar no le quita el sello).
     */
    public function visitedToday(Customer $customer): bool
    {
        return Sale::where('customer_id', $customer->id)
            ->where('counted_visit', true)
            ->whereNull('revert_type')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->exists();
    }

    /**
     * El canje pendiente (ganado pero aún no reclamado, sin vencer) más
     * antiguo de este cliente, si tiene alguno.
     */
    public function pendingRewardFor(Customer $customer): ?CustomerLoyaltyRedemption
    {
        return CustomerLoyaltyRedemption::with('loyaltyLevel')
            ->where('customer_id', $customer->id)
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
            return ['discount_amount' => 0, 'description' => null, 'free_product_id' => null, 'free_product_name' => null, 'free_product_in_cart' => false];
        }

        return $this->computeReward($level, $redemption->type, $baseAmount, $itemsData);
    }

    /**
     * @return array{type: string, discount_amount: float, description: string, free_product_id: ?int, free_product_name: ?string, free_product_in_cart: bool}
     */
    protected function computeReward(LoyaltyLevel $level, string $type, float $baseAmount, array $itemsData): array
    {
        if ($type === 'discount') {
            return [
                'type' => 'discount',
                'discount_amount' => round($baseAmount * ((float) $level->discount_percent / 100), 2),
                'description' => $level->discount_description ?: $level->discount_percent.'% de descuento',
                'free_product_id' => null,
                'free_product_name' => null,
                'free_product_in_cart' => false,
            ];
        }

        $result = [
            'type' => 'gift',
            'discount_amount' => 0,
            'description' => $level->gift_description ?: 'Producto gratis',
            'free_product_id' => $level->free_product_id,
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
        if (! ($preview['counts_visit'] ?? true)) {
            return; // ya recibió su sello de hoy
        }

        $sale->forceFill(['counted_visit' => true])->save();

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
            $customer->update($this->afterFullCard($customer));
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
