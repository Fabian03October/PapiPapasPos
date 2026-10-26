<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Manda un aviso de "Mi tarjeta":
 * - a un cliente (todos sus celulares), o
 * - a una tanda de celulares ($subscriptionIds), o
 * - sin cliente ni tanda: a TODOS, repartido en tandas de 200, cada una en
 *   su propio job. Así un envío grande no rebasa el tiempo del worker, y si
 *   una tanda falla solo se reintenta esa (los avisos llevan "tag": si a un
 *   celular le llega dos veces, el teléfono muestra uno solo).
 * En cola y después del commit: la venta nunca espera.
 */
class SendWebPushJob implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @param  array<int, int>|null  $subscriptionIds */
    public function __construct(
        public ?int $customerId,
        public string $title,
        public string $body,
        public ?string $tag = null,
        public ?array $subscriptionIds = null,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WebPushService $push): void
    {
        if ($this->subscriptionIds !== null) {
            $push->sendToSubscriptions($this->subscriptionIds, $this->title, $this->body, $this->tag);

            return;
        }

        if ($this->customerId === null) {
            foreach ($push->subscriptionBatches() as $ids) {
                static::dispatch(null, $this->title, $this->body, $this->tag, $ids);
            }

            return;
        }

        if ($customer = Customer::find($this->customerId)) {
            $push->sendToCustomer($customer, $this->title, $this->body, $this->tag);
        }
    }
}
