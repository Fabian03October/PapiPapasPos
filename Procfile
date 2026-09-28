web: bash deploy.sh && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan schedule:work & php artisan queue:work --tries=3 --max-time=3600 --sleep=3
