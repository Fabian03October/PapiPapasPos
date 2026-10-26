<?php

namespace App\Providers;

use App\Http\Middleware\WorkerWatchdog;
use App\Jobs\SendWebPushJob;
use App\Mail\Transport\BrevoApiTransport;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Observers\CustomerObserver;
use App\Services\Wallet\WalletNotifier;
use App\Services\WebPushService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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
        // Idioma: con APP_LOCALE=es_MX se busca es_MX, luego "es" y al final
        // el de respaldo (en). Filament trae su traducción como "es" (no
        // es_MX), así que sin esto el panel salía en inglés ("Search", "Per
        // page", "Drag & Drop..."). El respaldo en inglés se conserva porque
        // los mensajes base de Laravel solo vienen en inglés.
        $this->app['translator']->determineLocalesUsing(function (array $locales): array {
            $chain = [];
            foreach ($locales as $locale) {
                $chain[] = $locale;
                if (str_contains($locale, '_')) {
                    $chain[] = strstr($locale, '_', true);
                }
            }

            return $chain;
        });

        // El texto del campo para subir archivos viene fijo en inglés dentro
        // del componente ("Drag & Drop your files or Browse").
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload
            ->placeholder('Arrastra tu archivo aquí o <span class="filepond--label-action">búscalo</span>'));

        Customer::observe(CustomerObserver::class);

        // "Señal de vida" del worker para el vigilante (WorkerWatchdog): a lo
        // más una escritura por minuto, mientras la cola esté trabajando.
        Queue::looping(function (): void {
            static $last = 0;
            if (time() - $last >= 60) {
                $last = time();
                Cache::put(WorkerWatchdog::HEARTBEAT_KEY, time(), now()->addDay());
            }
        });

        // Ganar, cobrar o deshacer un premio cambia lo que dice la tarjeta
        // de Wallet ("Premio por cobrar"), aunque las visitas no cambien.
        $syncWallet = function (CustomerLoyaltyRedemption $redemption): void {
            if ($redemption->customer) {
                app(WalletNotifier::class)->customerChanged($redemption->customer);
            }
        };
        CustomerLoyaltyRedemption::saved($syncWallet);

        // "Mi tarjeta": aviso al ganar un premio que queda por cobrar.
        CustomerLoyaltyRedemption::created(function (CustomerLoyaltyRedemption $redemption): void {
            if ($redemption->status === 'pending' && config('services.webpush.reward_earned')
                && app(WebPushService::class)->isEnabled()) {
                SendWebPushJob::dispatch($redemption->customer_id, '🎉 ¡Ganaste tu premio!',
                    "{$redemption->rewardLabel()}. Cóbralo en tu próxima visita (vence el "
                    .$redemption->expires_at->copy()->locale('es')->translatedFormat('j \\d\\e F').').', "premio-{$redemption->id}");
            }
        });
        CustomerLoyaltyRedemption::deleted($syncWallet);

        RateLimiter::for('registro', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Límites de intentos del PIN (son 4 dígitos: 10,000 combinaciones),
        // con el mensaje en español que muestran las pantallas.
        $tooMany = fn (string $message) => fn () => response()->json(['success' => false, 'message' => $message], 429);

        // "Mi tarjeta": por CLIENTE, no por IP. Las compañías celulares hacen
        // que muchos clientes compartan IP; con límite por IP, tras una promo
        // varios recibían "demasiadas solicitudes". Más un tope alto por IP
        // contra abusos.
        RateLimiter::for('mi-tarjeta', fn (Request $request) => [
            Limit::perMinute(60)->by('tarjeta:'.$request->route('qrCode')),
            Limit::perMinute(1000)->by('tarjeta-ip:'.$request->ip()),
        ]);

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
