<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use App\Services\LoyaltyService;
use App\Services\Wallet\LoyaltyCardContent;

/**
 * Todo lo que ve el cliente en "Mi tarjeta", en un solo arreglo. La página
 * web lo pinta y /mi-tarjeta/{qr}/datos lo da en JSON: es la base para que
 * más adelante una app móvil o la página de pedidos (pick up) muestren lo
 * mismo sin repetir lógica.
 */
class CustomerPortal
{
    /** Promociones que se siguen mostrando si se mandaron sin fecha de fin. */
    public const PROMO_DAYS_WITHOUT_END = 7;

    public function __construct(
        protected LoyaltyCardContent $content,
        protected GoogleWalletService $google,
    ) {}

    public function snapshot(Customer $customer): array
    {
        $card = $this->content->for($customer);
        $goal = LoyaltyService::VISITS_FOR_GIFT;

        // Las 8 casillas de sellos: cuáles van llenas y cuáles dan premio.
        $stamps = [];
        for ($i = 1; $i <= $goal; $i++) {
            $stamps[] = [
                'number' => $i,
                'filled' => $i <= $card['visits'],
                'reward' => match ($i) {
                    LoyaltyService::VISITS_FOR_DISCOUNT => 'discount',
                    LoyaltyService::VISITS_FOR_GIFT => 'gift',
                    default => null,
                },
            ];
        }

        return [
            ...$card,
            'level' => (int) ($customer->current_level ?: 1),
            'stamps' => $stamps,
            'promotions' => $this->promotions($customer),
            'google_wallet_url' => $card['active'] ? $this->google->generateSaveLink($customer) : null,
            'design' => CardDesign::current(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Mensajes enviados desde el panel (a todos o a este cliente) que siguen
     * vigentes: los mismos que llegan a Google Wallet.
     *
     * @return array<int, array{title: string, body: string, date: string}>
     */
    public function promotions(Customer $customer): array
    {
        return WalletMessage::query()
            ->where('status', 'sent')
            ->where(fn ($q) => $q->where('audience', 'all')
                ->orWhere(fn ($q) => $q->where('audience', 'customer')->where('customer_id', $customer->id)))
            ->where(fn ($q) => $q->where('ends_at', '>', now())
                ->orWhere(fn ($q) => $q->whereNull('ends_at')->where('created_at', '>', now()->subDays(self::PROMO_DAYS_WITHOUT_END))))
            // Las que el cliente quitó de su tarjeta (✕).
            ->whereNotIn('id', fn ($q) => $q->select('wallet_message_id')->from('customer_hidden_messages')->where('customer_id', $customer->id))
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'body', 'created_at'])
            ->map(fn (WalletMessage $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'body' => $m->body,
                'date' => $m->created_at->copy()->locale('es')->translatedFormat('j \d\e F'),
            ])
            ->all();
    }
}
