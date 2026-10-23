#!/usr/bin/env bash
# Servicio "worker" de Railway (start command: bash worker.sh).
# Atiende la cola (actualizaciones de Google/Apple Wallet, mensajes) y el
# programador de tareas (respaldo diario de las 3 a.m.). Si alguno se cae,
# se vuelve a levantar solo en 5 segundos; si este script muere, Railway
# reinicia el servicio.
set -u

php artisan config:cache >/dev/null 2>&1 || true

(while true; do
    php artisan schedule:work
    echo "[worker] schedule:work terminó ($?); reiniciando en 5s" >&2
    sleep 5
done) &

while true; do
    # --max-time: se reinicia cada hora para soltar memoria.
    php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    echo "[worker] queue:work terminó ($?); reiniciando en 5s" >&2
    sleep 5
done
