<?php

namespace App\Observers;

use App\Jobs\DeactivateGoogleWalletObjectJob;
use App\Jobs\SyncGoogleWalletObjectJob;
use App\Models\Customer;
use App\Services\GoogleWalletService;

class CustomerObserver
{
    public function updated(Customer $customer): void
    {
        // Solo lo que se ve en la tarjeta. Con Wallet apagado no se encola
        // nada (si no, los jobs se acumulan sin hacer nada).
        if ($customer->wasChanged(['current_visits', 'current_level', 'name', 'is_active'])
            && app(GoogleWalletService::class)->isEnabled()) {
            SyncGoogleWalletObjectJob::dispatch($customer);
        }
    }

    public function deleted(Customer $customer): void
    {
        if ($customer->qr_code && app(GoogleWalletService::class)->isEnabled()) {
            DeactivateGoogleWalletObjectJob::dispatch(app(GoogleWalletService::class)->objectId($customer));
        }
    }
}
