<?php

namespace App\Console\Commands;

use App\Services\GoogleWalletService;
use Illuminate\Console\Command;

class SyncGoogleWalletClass extends Command
{
    protected $signature = 'wallet:sync-class {--force : No preguntar antes de sobrescribir}';

    protected $description = 'Crea o actualiza la LoyaltyClass de Google Wallet (plantilla del programa de fidelidad)';

    public function handle(GoogleWalletService $wallet): int
    {
        if (! $wallet->isEnabled()) {
            $this->error('Google Wallet está deshabilitado o le faltan credenciales (revisa GOOGLE_WALLET_* en .env).');

            return self::FAILURE;
        }

        // La clase ya está aprobada y diseñada en la consola de Google Pay &
        // Wallet. Esto la REEMPLAZA con lo guardado aquí (nombre, color,
        // logo) y puede mandarla a revisión otra vez.
        if (! $this->option('force') && ! $this->confirm(
            'Esto reemplaza el diseño de la tarjeta que está en la consola de Google (nombre, color y logo) con el del sistema. ¿Continuar?'
        )) {
            $this->info('No se cambió nada.');

            return self::SUCCESS;
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
