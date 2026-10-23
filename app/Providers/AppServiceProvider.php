<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Observers\CustomerObserver;
use App\Services\Wallet\WalletNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Customer::observe(CustomerObserver::class);

        // Ganar, cobrar o deshacer un premio cambia lo que dice la tarjeta
        // de Wallet ("Premio por cobrar"), aunque las visitas no cambien.
        $syncWallet = function (CustomerLoyaltyRedemption $redemption): void {
            if ($redemption->customer) {
                app(WalletNotifier::class)->customerChanged($redemption->customer);
            }
        };
        CustomerLoyaltyRedemption::saved($syncWallet);
        CustomerLoyaltyRedemption::deleted($syncWallet);

        RateLimiter::for('registro', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Límites de intentos del PIN (son 4 dígitos: 10,000 combinaciones),
        // con el mensaje en español que muestran las pantallas.
        $tooMany = fn (string $message) => fn () => response()->json(['success' => false, 'message' => $message], 429);

        RateLimiter::for('login-pin', fn (Request $request) => Limit::perMinute(10)->by($request->ip())
            ->response($tooMany('Demasiados intentos. Espera un minuto e inténtalo de nuevo.')));

        RateLimiter::for('olvide-pin', fn (Request $request) => Limit::perMinutes(10, 5)->by($request->ip())
            ->response($tooMany('Ya se enviaron varios correos. Espera unos minutos.')));

        RateLimiter::for('restablecer-pin', fn (Request $request) => Limit::perMinute(10)->by($request->ip())
            ->response($tooMany('Demasiados intentos. Espera un minuto e inténtalo de nuevo.')));

        Mail::extend('brevo', function (array $config) {
            return new BrevoApiTransport($config['key']);
        });

        // @publicScript('js/print.js'): los JS de public/js no pasan por Vite, así
        // que sin versión el navegador seguía usando la copia vieja después
        // de un deploy (ej. la impresora Bluetooth sin los cambios nuevos).
        Blade::directive('publicScript', function (string $path) {
            return "<?php echo '<script src=\"' . e(asset({$path}) . '?v=' . @filemtime(public_path({$path}))) . '\"></script>'; ?>";
        });
    }
}
