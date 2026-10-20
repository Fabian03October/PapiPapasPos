<?php

namespace App\Jobs;

use App\Services\GoogleWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Cliente borrado: su tarjeta en Google Wallet queda inactiva. Recibe el ID (el cliente ya no existe). */
class DeactivateGoogleWalletObjectJob implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public string $objectId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(GoogleWalletService $wallet): void
    {
        $wallet->deactivateObject($this->objectId);
    }
}
