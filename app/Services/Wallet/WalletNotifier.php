<?php

namespace App\Services\Wallet;

use App\Jobs\DeactivateGoogleWalletObjectJob;
use App\Jobs\SyncGoogleWalletObjectJob;
use App\Jobs\UpdateAppleWalletPassJob;
use App\Models\Customer;
use App\Services\AppleWalletService;
use App\Services\GoogleWalletService;

/**
 * Único punto que avisa a los wallets que la tarjeta de un cliente cambió
 * (sellos, nivel, premio, nombre, baja). Cada proveedor encendido recibe su
 * propio job en cola: si uno falla o está apagado, el otro no se entera y la
 * venta nunca espera.
 */
class WalletNotifier
{
    public function __construct(
        protected GoogleWalletService $google,
        protected AppleWalletService $apple,
    ) {}

    public function customerChanged(Customer $customer): void
    {
        if ($this->google->isEnabled()) {
            SyncGoogleWalletObjectJob::dispatch($customer);
        }

        if ($this->apple->isEnabled() && $this->apple->hasPass($customer)) {
            UpdateAppleWalletPassJob::dispatch($customer);
        }
    }

    public function customerDeleted(Customer $customer): void
    {
        if ($customer->qr_code && $this->google->isEnabled()) {
            DeactivateGoogleWalletObjectJob::dispatch($this->google->objectId($customer));
        }
        // Apple: el pase se queda como está (no hay cliente del cual leer
        // datos), pero su QR ya no encuentra a nadie en caja. Para "apagar"
        // una tarjeta conviene dar de baja al cliente (is_active), no borrarlo.
    }
}
