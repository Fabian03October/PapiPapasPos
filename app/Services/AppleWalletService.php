<?php

namespace App\Services;

use App\Models\AppleWalletPass;
use App\Models\Customer;
use App\Models\WalletCardSettings;
use App\Services\Wallet\LoyaltyCardContent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PKPass\PKPass;

/**
 * Tarjeta de fidelidad en Apple Wallet (.pkpass), con el mismo contenido que
 * la de Google (LoyaltyCardContent). Apagado mientras no haya certificados
 * (ver docs/apple-wallet.md).
 *
 * Cómo se actualiza (protocolo "Wallet Web Service" de Apple):
 *  1. El iPhone guarda el pase y se registra en webServiceURL (AppleWalletController).
 *  2. Cambian los sellos → UpdateAppleWalletPassJob marca el pase como modificado
 *     y manda un aviso VACÍO por APNs a cada iPhone registrado.
 *  3. El iPhone pide el pase nuevo a webServiceURL y lo reemplaza solo.
 */
class AppleWalletService
{
    public function __construct(protected LoyaltyCardContent $content) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.apple_wallet.enabled')
            && filled(config('services.apple_wallet.pass_type_identifier'))
            && filled(config('services.apple_wallet.team_identifier'))
            && filled(config('services.apple_wallet.certificate_base64'));
    }

    public function passTypeIdentifier(): string
    {
        return (string) config('services.apple_wallet.pass_type_identifier');
    }

    public function hasPass(Customer $customer): bool
    {
        return AppleWalletPass::where('customer_id', $customer->id)->exists();
    }

    /** El registro del pase del cliente (se crea la primera vez que lo descarga). */
    public function passFor(Customer $customer): AppleWalletPass
    {
        return AppleWalletPass::firstOrCreate(
            ['customer_id' => $customer->id],
            ['serial_number' => $customer->ensureQrCode(), 'authentication_token' => Str::random(48)],
        );
    }

    /** pass.json: la tarjeta en el formato de Apple (storeCard). */
    public function passJson(Customer $customer, AppleWalletPass $pass): array
    {
        $card = $this->content->for($customer);
        $settings = WalletCardSettings::current();

        $back = [
            ['key' => 'member_since', 'label' => 'Miembro desde', 'value' => $card['member_since']],
            ['key' => 'how', 'label' => '¿Cómo funciona?', 'value' => "Muestra este código en caja en cada visita. A las {$card['goal']} visitas ganas tu premio."],
        ];
        if ($settings->contact_info) {
            $back[] = ['key' => 'contact', 'label' => 'Contacto', 'value' => $settings->contact_info];
        }

        $auxiliary = [];
        if ($card['pending_reward']) {
            // changeMessage: el iPhone avisa en la pantalla de bloqueo al ganarlo.
            $auxiliary[] = ['key' => 'pending_reward', 'label' => 'PREMIO POR COBRAR', 'value' => $card['pending_reward'], 'changeMessage' => '🎁 %@'];
        }

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $this->passTypeIdentifier(),
            'teamIdentifier' => (string) config('services.apple_wallet.team_identifier'),
            'serialNumber' => $pass->serial_number,
            'organizationName' => (string) config('services.apple_wallet.organization_name'),
            'description' => (string) config('services.apple_wallet.description'),
            'logoText' => (string) ($settings->program_name ?: config('services.apple_wallet.organization_name')),
            'foregroundColor' => (string) config('services.apple_wallet.foreground_color'),
            'backgroundColor' => (string) config('services.apple_wallet.background_color'),
            'labelColor' => (string) config('services.apple_wallet.label_color'),
            // Apple solo acepta https: en producción es la URL de Railway.
            'webServiceURL' => url('/wallet/apple'),
            'authenticationToken' => $pass->authentication_token,
            // Cliente dado de baja: el pase se ve anulado en su iPhone.
            'voided' => ! $card['active'],
            'barcodes' => [[
                'format' => 'PKBarcodeFormatQR',
                'message' => $card['qr'],          // lo mismo que escanea la caja
                'messageEncoding' => 'iso-8859-1',
            ]],
            'storeCard' => [
                'headerFields' => [
                    ['key' => 'visits', 'label' => 'VISITAS', 'value' => $card['visits_label'], 'changeMessage' => 'Ya llevas %@ visitas'],
                ],
                'primaryFields' => [
                    ['key' => 'name', 'label' => 'CLIENTE', 'value' => $card['name']],
                ],
                'secondaryFields' => [
                    ['key' => 'next_reward', 'label' => 'PRÓXIMO PREMIO', 'value' => $card['next_reward']],
                ],
                'auxiliaryFields' => $auxiliary,
                'backFields' => $back,
            ],
        ];
    }

    /** El archivo .pkpass firmado (bytes). */
    public function generate(Customer $customer): string
    {
        $pass = $this->passFor($customer);

        $pkpass = new PKPass;
        $pkpass->setCertificateString((string) base64_decode((string) config('services.apple_wallet.certificate_base64'), true));
        $pkpass->setCertificatePassword((string) config('services.apple_wallet.certificate_password'));
        $pkpass->setData($this->passJson($customer, $pass));

        foreach ($this->images() as $name => $png) {
            $pkpass->addFileContent($png, $name);
        }

        return $pkpass->create(false);
    }

    /**
     * Imágenes del pase (PNG): el logo de "Diseño de tarjeta" si existe; si
     * no, el genérico de resources/wallet/apple. icon.png es obligatorio.
     *
     * @return array<string, string> nombre => bytes PNG
     */
    public function images(): array
    {
        $settings = WalletCardSettings::current();
        $source = null;

        if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
            $source = Storage::disk('public')->get($settings->logo_path);
        }

        $source ??= (string) file_get_contents(resource_path('wallet/apple/logo.png'));

        return [
            'icon.png' => $this->fit($source, 29, 29),
            'icon@2x.png' => $this->fit($source, 58, 58),
            'icon@3x.png' => $this->fit($source, 87, 87),
            'logo.png' => $this->fit($source, 160, 50),
            'logo@2x.png' => $this->fit($source, 320, 100),
            'logo@3x.png' => $this->fit($source, 480, 150),
        ];
    }

    /** Escala la imagen para que quepa en w×h (sin deformar), fondo transparente. */
    protected function fit(string $bytes, int $w, int $h): string
    {
        $src = @imagecreatefromstring($bytes);

        if (! $src) {
            $src = imagecreatefromstring((string) file_get_contents(resource_path('wallet/apple/logo.png')));
        }

        $scale = min($w / imagesx($src), $h / imagesy($src));
        $nw = max(1, (int) round(imagesx($src) * $scale));
        $nh = max(1, (int) round(imagesy($src) * $scale));

        $dst = imagecreatetruecolor($w, $h);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, intdiv($w - $nw, 2), intdiv($h - $nh, 2), 0, 0, $nw, $nh, imagesx($src), imagesy($src));

        ob_start();
        imagepng($dst);

        return (string) ob_get_clean();
    }

    /**
     * Avisa a cada iPhone que tiene el pase que hay versión nueva. El aviso
     * va VACÍO ({}): el iPhone pide el pase a webServiceURL y lo reemplaza.
     * Se autentica con el mismo certificado del Pass Type ID (Apple no acepta
     * la llave .p8 para pases).
     *
     * Errores pasajeros (sin red, 429, 5xx) lanzan excepción para que el job
     * reintente; un iPhone que ya no tiene el pase (410/400) se borra.
     */
    public function pushUpdate(AppleWalletPass $pass): void
    {
        $p12 = (string) base64_decode((string) config('services.apple_wallet.certificate_base64'), true);
        $host = rtrim((string) config('services.apple_wallet.apns_host'), '/');

        foreach ($pass->registrations as $registration) {
            try {
                $response = Http::withOptions([
                    'version' => 2.0,
                    'curl' => [
                        CURLOPT_SSLCERT_BLOB => $p12,
                        CURLOPT_SSLCERTTYPE => 'P12',
                        CURLOPT_SSLCERTPASSWD => (string) config('services.apple_wallet.certificate_password'),
                    ],
                ])
                    ->withHeaders(['apns-topic' => $this->passTypeIdentifier()])
                    ->timeout(15)
                    ->withBody('{}', 'application/json')
                    ->post("{$host}/3/device/{$registration->push_token}");
            } catch (ConnectionException $e) {
                Log::warning('Apple Wallet: sin conexión con APNs, se reintentará', ['pass_id' => $pass->id, 'error' => $e->getMessage()]);

                throw $e;
            }

            if ($response->status() === 410 || ($response->status() === 400 && str_contains($response->body(), 'BadDeviceToken'))) {
                $registration->delete();

                continue;
            }

            if ($response->status() === 429 || $response->serverError()) {
                throw new \RuntimeException("APNs respondió {$response->status()}");
            }

            if ($response->failed()) {
                Log::error('Apple Wallet: APNs rechazó el aviso', ['pass_id' => $pass->id, 'status' => $response->status(), 'body' => $response->body()]);
            }
        }
    }
}
