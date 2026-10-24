<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PushSubscription;
use App\Models\WalletMessage;
use App\Services\Portal\CardDesign;
use App\Services\Portal\CustomerPortal;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Mi tarjeta": la página del cliente (instalable como app, funciona sin
 * internet). Se entra con el enlace secreto del registro (UUID del QR), sin
 * contraseña. Vive bajo /mi-tarjeta/: el service worker solo controla esa
 * ruta, nunca las pantallas del POS.
 */
class CustomerPortalController extends Controller
{
    public function __construct(protected CustomerPortal $portal) {}

    public function show(string $qrCode)
    {
        $customer = $this->customer($qrCode);
        $card = $this->portal->snapshot($customer);

        return response()
            ->view('mi-tarjeta.show', [
                'customer' => $customer,
                'card' => $card,
                'design' => $card['design'],
                'qrSvg' => $customer->loyaltyQrBase64(320),
                'pushKey' => app(WebPushService::class)->publicKey(),
            ])
            // Que siempre pida la versión nueva cuando hay internet (sin
            // internet la da el service worker).
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Lo mismo en JSON: para refrescar la página y para una futura app móvil. */
    public function data(string $qrCode)
    {
        return response()
            ->json($this->portal->snapshot($this->customer($qrCode)))
            ->header('Cache-Control', 'no-store');
    }

    /** Manifiesto de la app: al instalarla abre directo la tarjeta de ESTE cliente. */
    public function manifest(string $qrCode)
    {
        $customer = $this->customer($qrCode);
        $home = route('mi-tarjeta', $customer->qr_code, false);

        return response()->json([
            'name' => "Papi's Papas",
            'short_name' => "Papi's Papas",
            'description' => 'Tu tarjeta de sellos de Papi\'s Papas',
            'id' => $home,
            'start_url' => $home.'?app=1',
            'scope' => $home,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => CardDesign::current()['bg'],
            'theme_color' => CardDesign::current()['bg'],
            'lang' => 'es-MX',
            'icons' => [
                ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/pwa/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** El celular aceptó avisos: guarda su "dirección" (endpoint + llaves). */
    public function subscribe(Request $request, string $qrCode)
    {
        $customer = $this->customer($qrCode);

        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // Si ese celular estaba ligado a otro cliente (cambió de tarjeta), se mueve.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'customer_id' => $customer->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['ok' => true], 201);
    }

    /** Desactivó los avisos desde la tarjeta. */
    public function unsubscribe(Request $request, string $qrCode)
    {
        $customer = $this->customer($qrCode);
        $endpoint = (string) $request->input('endpoint');

        PushSubscription::where('customer_id', $customer->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * El cliente quita una promoción de SU tarjeta (✕). Solo se puede ocultar
     * un mensaje que de verdad le llegó (a todos o a él).
     */
    public function hideMessage(string $qrCode, WalletMessage $message)
    {
        $customer = $this->customer($qrCode);

        abort_unless($message->audience === 'all' || (int) $message->customer_id === $customer->id, 404);

        DB::table('customer_hidden_messages')->insertOrIgnore([
            'customer_id' => $customer->id,
            'wallet_message_id' => $message->id,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    protected function customer(string $qrCode): Customer
    {
        // Dados de baja también entran: ven su tarjeta marcada como inactiva.
        return Customer::where('qr_code', $qrCode)->firstOrFail();
    }
}
