<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo diario de la base de datos (solo la BD, no el código de la app -
// eso ya vive en git). Se genera, se manda por correo
// (App\Listeners\EmailDatabaseBackup, disparado por el evento
// BackupWasSuccessful de spatie/laravel-backup) y se borra del disco local
// de inmediato - el correo es el respaldo real, fuera de Railway.
Schedule::command('backup:run --only-db')->daily()->at('03:00');

// Avisos de "Mi tarjeta" que dependen del día: premio que vence mañana y
// cumpleaños. A mediodía (nunca de noche).
Schedule::command('fidelidad:avisos-diarios')->dailyAt('12:00')->timezone('America/Mexico_City');
