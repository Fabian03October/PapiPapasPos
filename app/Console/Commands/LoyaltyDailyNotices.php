<?php

namespace App\Console\Commands;

use App\Jobs\SendWebPushJob;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Services\WebPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Avisos automáticos de "Mi tarjeta" que dependen del día: premio que vence
 * mañana y cumpleaños. Solo a clientes con avisos activos; cada uno se puede
 * apagar en Railway.
 *
 * Corre cada hora de 11 a 20 h: cada aviso queda anotado (caché) al mandarse,
 * así que nunca sale dos veces, y si el worker estuvo caído a mediodía se
 * manda en cuanto vuelva.
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
                // Vence mañana (o lo que queda de hoy, si un día no corrió).
                ->whereBetween('expires_at', [now(), now()->addDay()->endOfDay()])
                ->whereHas('customer', fn ($q) => $q->where('is_active', true)->has('pushSubscriptions'))
                ->each(function (CustomerLoyaltyRedemption $redemption) use (&$sent) {
                    if (! Cache::add("aviso-vence:{$redemption->id}", true, now()->addDays(3))) {
                        return; // ya se le avisó
                    }
                    SendWebPushJob::dispatch($redemption->customer_id, '⏰ Tu premio vence mañana',
                        "{$redemption->rewardLabel()}. Pídelo en caja antes de que se venza.", "vence-{$redemption->id}");
                    $sent++;
                });
        }

        if (config('services.webpush.birthday')) {
            // Quien nació el 29 de febrero, en años no bisiestos: el 28.
            $leapBaby = now()->month === 2 && now()->day === 28 && ! now()->isLeapYear();

            Customer::where('is_active', true)
                ->whereNotNull('birthdate')
                ->whereMonth('birthdate', now()->month)
                ->where(fn ($q) => $q->whereDay('birthdate', now()->day)
                    ->when($leapBaby, fn ($q) => $q->orWhereDay('birthdate', 29)))
                ->has('pushSubscriptions')
                ->each(function (Customer $customer) use (&$sent) {
                    if (! Cache::add("aviso-cumple:{$customer->id}:".now()->year, true, now()->addDays(2))) {
                        return; // ya se le felicitó este año
                    }
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
