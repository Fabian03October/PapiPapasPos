<?php

namespace App\Listeners;

use App\Mail\DatabaseBackupMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * El disco local de Railway es efímero (se borra en cada redeploy), así que
 * el respaldo real "fuera de Railway" es este correo, no el archivo en
 * disco. Por eso se borra el archivo local justo después de mandarlo -
 * nunca se acumulan respaldos viejos en el contenedor.
 */
class EmailDatabaseBackup
{
    public function handle(BackupWasSuccessful $event): void
    {
        $backup = $event->backupDestination->newestBackup();

        if (! $backup) {
            return;
        }

        $notifyEmail = config('backup.notifications.mail.to');

        if (! $notifyEmail || $notifyEmail === 'your@example.com') {
            Log::warning('Respaldo de base de datos creado pero BACKUP_NOTIFY_EMAIL no está configurado - no se pudo enviar.');

            return;
        }

        $contents = stream_get_contents($backup->stream());
        $sizeInMb = $backup->sizeInBytes() / 1024 / 1024;

        Mail::to($notifyEmail)->send(
            new DatabaseBackupMail($contents, basename($backup->path()), $sizeInMb)
        );

        $backup->delete();
    }
}
