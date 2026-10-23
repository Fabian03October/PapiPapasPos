<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
        return $this->send($customer->pushSubscriptions()->get(), $title, $body, $tag);
    }

    /** A todos los celulares con avisos activos (clientes activos), en tandas. */
    public function sendToAll(string $title, string $body, ?string $tag = null): int
    {
        $sent = 0;

        PushSubscription::with('customer:id,qr_code,is_active')
            ->whereHas('customer', fn ($q) => $q->where('is_active', true))
            ->chunkById(200, function (Collection $subscriptions) use (&$sent, $title, $body, $tag) {
                $sent += $this->send($subscriptions, $title, $body, $tag);
            });

        return $sent;
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
                Log::warning('Web Push: no se pudo mandar el aviso', ['reason' => $report->getReason()]);
            }
        }

        return $sent;
    }

    protected function client(): WebPush
    {
        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) config('services.webpush.subject'),
            'publicKey' => (string) config('services.webpush.public_key'),
            'privateKey' => (string) config('services.webpush.private_key'),
        ]]);
        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
