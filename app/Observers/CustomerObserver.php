<?php

namespace App\Observers;

use App\Jobs\SyncGoogleWalletObjectJob;
use App\Models\Customer;

class CustomerObserver
{
    public function updated(Customer $customer): void
    {
        if ($customer->wasChanged(['current_visits', 'current_level', 'name'])) {
            SyncGoogleWalletObjectJob::dispatch($customer);
        }
    }
}
