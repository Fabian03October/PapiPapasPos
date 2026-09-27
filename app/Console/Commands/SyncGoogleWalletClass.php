<?php

namespace App\Console\Commands;

use App\Services\GoogleWalletService;
use Illuminate\Console\Command;

class SyncGoogleWalletClass extends Command
{
    protected $signature = 'wallet:sync-class';

    protected $description = 'Crea o actualiza la LoyaltyClass de Google Wallet (plantilla del programa de fidelidad)';

    public function handle(GoogleWalletService $wallet): int
    {
        if (! $wallet->isEnabled()) {
            $this->error('Google Wallet está deshabilitado o le faltan credenciales (revisa GOOGLE_WALLET_* en .env).');

            return self::FAILURE;
        }

        $result = $wallet->upsertClass();

        if (! $result['success']) {
            $this->error('No se pudo crear/actualizar la clase: '.$result['error']);

            return self::FAILURE;
        }

        $this->info('Clase de fidelidad sincronizada con Google Wallet.');

        return self::SUCCESS;
    }
}
