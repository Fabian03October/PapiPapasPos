<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\GoogleWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleWalletObjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Customer $customer)
    {
    }

    public function handle(GoogleWalletService $wallet): void
    {
        $wallet->syncCustomerObject($this->customer);
    }
}
