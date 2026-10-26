<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Avisos de "Mi tarjeta" (Web Push, estándar del navegador). Gratis en
 * Android y en iPhone (iOS 16.4+, con la tarjeta en la pantalla de inicio).
 * Se autentica con un par de llaves VAPID propias (php artisan webpush:llaves).
 */
class WebPushService
{
    public function isEnabled(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('services.webpush.public_key');
    }

    /** @return int cuántos celulares lo recibieron */
    public function sendToCustomer(Customer $customer, string $title, string $body, ?string $tag = null): int
    {
        $subscriptions = $customer->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            Log::error("Web Push: el cliente #{$customer->id} no tiene avisos activados en ningún celular; no se mandó \"{$title}\".");
        }

        return $this->send($subscriptions, $title, $body, $tag);
    }

    /**
     * IDs de los celulares con avisos activos (clientes activos), en tandas
     * de $size: cada tanda se manda en su propio job (ver SendWebPushJob).
     *
     * @return LazyCollection<int, array<int, int>>
     */
    public function subscriptionBatches(int $size = 200): LazyCollection
    {
        return PushSubscription::whereHas('customer', fn ($q) => $q->where('is_active', true))
            ->lazyById($size)
            ->pluck('id')
            ->chunk($size)
            ->map(fn ($ids) => $ids->values()->all());
    }

    /** @param  array<int, int>  $ids */
    public function sendToSubscriptions(array $ids, string $title, string $body, ?string $tag = null): int
    {
        return $this->send(PushSubscription::whereIn('id', $ids)->get(), $title, $body, $tag);
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    protected function send(Collection $subscriptions, string $title, string $body, ?string $tag): int
    {
        if (! $this->isEnabled() || $subscriptions->isEmpty()) {
            return 0;
        }

        $subscriptions->loadMissing('customer:id,qr_code');
        $webPush = $this->client();
        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription;
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => 'aes128gcm',
                ]),
                json_encode([
                    'title' => $title,
                    'body' => $body,
                    'tag' => $tag,
                    // Al tocar el aviso se abre la tarjeta de ESE cliente.
                    'url' => $subscription->customer ? route('mi-tarjeta', $subscription->customer->qr_code, false) : '/',
                ], JSON_UNESCAPED_UNICODE),
                ['TTL' => 60 * 60 * 24, 'urgency' => 'normal'],
            );
        }

        $sent = 0;
        foreach ($webPush->flush() as $report) {
            $subscription = $byEndpoint[$report->getEndpoint()] ?? null;

            if ($report->isSuccess()) {
                $sent++;
                $subscription?->forceFill(['last_sent_at' => now()])->save();
            } elseif ($report->isSubscriptionExpired()) {
                // Desinstaló la tarjeta o quitó el permiso: ya no se le manda.
                $subscription?->delete();
            } else {
                // Como error (Railway solo muestra errores): llaves distintas
                // entre la página y el worker dan 401/403 aquí.
                Log::error('Web Push: el servicio de avisos rechazó el aviso', [
                    'status' => $report->getResponse()?->getStatusCode(),
                    'reason' => $report->getReason(),
                    'servicio' => parse_url($report->getEndpoint(), PHP_URL_HOST),
                ]);
            }
        }

        return $sent;
    }

    protected function client(): WebPush
    {
        // Con el logger de Laravel, los avisos de la librería (ej. "instala
        // GMP o BCMath") se anotan en vez de lanzarse como error: sin esto,
        // Laravel los convertía en excepción y el envío fallaba.
        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) config('services.webpush.subject'),
            'publicKey' => (string) config('services.webpush.public_key'),
            'privateKey' => (string) config('services.webpush.private_key'),
        ]], logger: Log::channel(config('logging.default')));
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
