<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Genera el par de llaves de los avisos (una sola vez; van en Railway). */
class GenerateWebPushKeys extends Command
{
    protected $signature = 'webpush:llaves';

    protected $description = 'Genera las llaves VAPID para los avisos de Mi tarjeta';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('Pon estas variables en Railway (página y worker). No las compartas:');
        $this->newLine();
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('Si las cambias después, los clientes tendrán que volver a activar los avisos.');

        return self::SUCCESS;
    }
}
