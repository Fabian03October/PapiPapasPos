#!/usr/bin/env bash
# Se corre en cada arranque del servicio "web" en Railway, antes de levantar
# el servidor. El symlink de storage y los cachés viven en el filesystem
# efímero del contenedor, así que hay que rehacerlos en cada deploy aunque
# ya existieran en el deploy anterior.
set -e

php artisan migrate --force
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
