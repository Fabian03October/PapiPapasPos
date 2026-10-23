<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Manda un aviso de "Mi tarjeta" a un cliente (todos sus celulares) o, sin
 * cliente, a todos. En cola y después del commit: la venta nunca espera.
 */
class SendWebPushJob implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public ?int $customerId,
        public string $title,
        public string $body,
        public ?string $tag = null,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WebPushService $push): void
    {
        if ($this->customerId === null) {
            $push->sendToAll($this->title, $this->body, $this->tag);

            return;
        }

        if ($customer = Customer::find($this->customerId)) {
            $push->sendToCustomer($customer, $this->title, $this->body, $this->tag);
        }
    }
}
