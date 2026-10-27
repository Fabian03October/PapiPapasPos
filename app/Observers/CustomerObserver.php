<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\Wallet\WalletNotifier;

class CustomerObserver
{
    public function updated(Customer $customer): void
    {
        // Solo lo que se ve en la tarjeta.
        if ($customer->wasChanged(['current_visits', 'current_level', 'cards_completed', 'name', 'is_active'])) {
            app(WalletNotifier::class)->customerChanged($customer);
        }
    }

    public function deleted(Customer $customer): void
    {
        app(WalletNotifier::class)->customerDeleted($customer);
    }
}
