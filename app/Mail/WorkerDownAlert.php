<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/** Correo del vigilante (WorkerWatchdog): el worker no está funcionando. */
class WorkerDownAlert extends Mailable
{
    /** @param  array<int, string>  $problems */
    public function __construct(public array $problems) {}

    public function build(): static
    {
        $body = "El sistema de Papi's Papas detectó un problema con el worker (el proceso que trabaja en segundo plano):\n\n- "
            .implode("\n- ", $this->problems)
            ."\n\nMientras no se arregle: no se actualizan las tarjetas, no salen los avisos ni los mensajes, y no se hace el respaldo de las 3 a.m.\n\n"
            .'Qué revisar en Railway (sistema '.config('app.url')."):\n"
            ."1. Que el servicio del worker esté Active/Online.\n"
            ."2. Que su Custom Start Command sea: bash worker.sh (y el Pre-deploy Command vacío).\n"
            ."3. Sus Deploy Logs, por si hay un error.\n\n"
            .'Este aviso se repite como máximo cada 2 horas mientras siga el problema.';

        return $this->subject("⚠️ Papi's Papas: el worker no está funcionando")
            ->html(nl2br(e($body)));
    }
}
