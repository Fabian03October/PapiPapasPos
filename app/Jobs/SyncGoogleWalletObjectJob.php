<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\GoogleWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Manda a Google Wallet las visitas/nivel/premio al día del cliente.
 *
 * - Después del commit: si la venta se revierte, no se manda nada.
 * - Reintenta con espera creciente si Google falla; la venta nunca espera.
 * - Al correr toma al cliente fresco de la base, así que aunque se encolen
 *   varios seguidos, todos mandan el dato más reciente.
 */
class SyncGoogleWalletObjectJob implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** Si el cliente se borró antes de que corra el job, se descarta sin error. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Customer $customer) {}

    /** @return array<int, int> segundos entre reintentos */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(GoogleWalletService $wallet): void
    {
        $wallet->syncCustomerObject($this->customer);
    }
}
