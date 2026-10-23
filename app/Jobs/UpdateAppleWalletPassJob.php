<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\AppleWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * La tarjeta de Apple Wallet del cliente cambió: se marca el pase como
 * modificado (el iPhone compara esa fecha) y se avisa por APNs a cada iPhone
 * registrado. Mismas reglas que el de Google: después del commit, uno por
 * cliente en cola, reintentos con espera.
 */
class UpdateAppleWalletPassJob implements ShouldBeUniqueUntilProcessing, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public bool $deleteWhenMissingModels = true;

    public int $uniqueFor = 120;

    public function __construct(public Customer $customer) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function uniqueId(): string
    {
        return (string) $this->customer->getKey();
    }

    public function handle(AppleWalletService $apple): void
    {
        if (! $apple->isEnabled() || ! ($pass = $this->customer->appleWalletPass)) {
            return;
        }

        // Solo en el primer intento: en un reintento ya está marcado.
        if ($this->attempts() === 1) {
            $pass->touch();
        }

        $apple->pushUpdate($pass->load('registrations'));
    }
}
