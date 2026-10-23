<?php

namespace App\Console\Commands;

use App\Jobs\SendWebPushJob;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Services\WebPushService;
use Illuminate\Console\Command;

/**
 * Avisos automáticos de "Mi tarjeta" que dependen del día (corre una vez al
 * día, a mediodía): premio que vence mañana y cumpleaños. Solo a clientes con
 * avisos activos; cada uno se puede apagar en Railway.
 */
class LoyaltyDailyNotices extends Command
{
    protected $signature = 'fidelidad:avisos-diarios';

    protected $description = 'Avisos de Mi tarjeta: premio que vence mañana y cumpleaños';

    public function handle(WebPushService $push): int
    {
        if (! $push->isEnabled()) {
            $this->info('Avisos apagados (faltan las llaves WEBPUSH_*).');

            return self::SUCCESS;
        }

        $sent = 0;

        if (config('services.webpush.reward_expiring')) {
            CustomerLoyaltyRedemption::with('loyaltyLevel')
                ->where('status', 'pending')
                ->whereBetween('expires_at', [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()])
                ->whereHas('customer', fn ($q) => $q->where('is_active', true)->has('pushSubscriptions'))
                ->each(function (CustomerLoyaltyRedemption $redemption) use (&$sent) {
                    SendWebPushJob::dispatch($redemption->customer_id, '⏰ Tu premio vence mañana',
                        "{$redemption->rewardLabel()}. Pídelo en caja antes de que se venza.", "vence-{$redemption->id}");
                    $sent++;
                });
        }

        if (config('services.webpush.birthday')) {
            Customer::where('is_active', true)
                ->whereNotNull('birthdate')
                ->whereMonth('birthdate', now()->month)
                ->whereDay('birthdate', now()->day)
                ->has('pushSubscriptions')
                ->each(function (Customer $customer) use (&$sent) {
                    $first = strtok((string) $customer->name, ' ');
                    SendWebPushJob::dispatch($customer->id, "🎂 ¡Feliz cumpleaños, {$first}!",
                        "Todo el equipo de Papi's Papas te desea un gran día. ¡Ven a celebrarlo con unas papas!", 'cumple-'.now()->year);
                    $sent++;
                });
        }

        $this->info("Avisos en cola: {$sent}");

        return self::SUCCESS;
    }
}
