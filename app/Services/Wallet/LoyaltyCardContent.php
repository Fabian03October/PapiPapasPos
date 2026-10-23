<?php

namespace App\Services\Wallet;

use App\Models\Customer;
use App\Services\LoyaltyService;

/**
 * Lo que dice la tarjeta de fidelidad de un cliente, igual para Google Wallet
 * y Apple Wallet: cada proveedor solo lo acomoda en su formato. Así las dos
 * tarjetas nunca dicen cosas distintas.
 *
 * Hace 2 consultas (nivel actual + premio por cobrar con su nivel ya cargado).
 */
class LoyaltyCardContent
{
    public function __construct(protected LoyaltyService $loyalty) {}

    /**
     * @return array{
     *     name: string, qr: string, active: bool, visits: int, goal: int,
     *     visits_label: string, member_since: string, next_reward: string,
     *     pending_reward: ?string
     * }
     */
    public function for(Customer $customer): array
    {
        $reward = $this->loyalty->nextRewardSummary($customer);
        $remaining = $reward['visits_remaining'];
        $goal = LoyaltyService::VISITS_FOR_GIFT;

        $pendingText = null;
        if ($pending = $this->loyalty->pendingRewardFor($customer)) {
            $pendingText = "{$pending->rewardLabel()} · vence el ".$pending->expires_at->copy()->locale('es')->translatedFormat('j \d\e F');
        }

        return [
            'name' => (string) $customer->name,
            'qr' => $customer->ensureQrCode(),
            'active' => $customer->is_active === null || (bool) $customer->is_active,
            'visits' => (int) $customer->current_visits,
            'goal' => $goal,
            'visits_label' => "{$customer->current_visits} de {$goal}",
            // En español fijo: la app puede estar en otro idioma (APP_LOCALE).
            'member_since' => $customer->created_at?->copy()->locale('es')->translatedFormat('F Y') ?? '',
            'next_reward' => $remaining === 1
                ? "{$reward['label']} · falta 1 visita"
                : "{$reward['label']} · faltan {$remaining} visitas",
            'pending_reward' => $pendingText,
        ];
    }
}
