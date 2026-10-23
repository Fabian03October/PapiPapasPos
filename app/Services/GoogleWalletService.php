<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WalletCardSettings;
use App\Services\Wallet\LoyaltyCardContent;
use Firebase\JWT\JWT;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleWalletService
{
    private const SCOPE = 'https://www.googleapis.com/auth/wallet_object.issuer';

    private const BASE_URL = 'https://walletobjects.googleapis.com/walletobjects/v1';

    private const TOKEN_CACHE_KEY = 'google_wallet_access_token';

    public function __construct(protected LoyaltyCardContent $content) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.google_wallet.enabled')
            && filled(config('services.google_wallet.issuer_id'))
            && filled(config('services.google_wallet.service_account_json'));
    }

    public function buildLoyaltyObjectPayload(Customer $customer): array
    {
        $card = $this->content->for($customer);

        $modules = [
            ['id' => 'member_since', 'header' => 'Miembro desde', 'body' => $card['member_since']],
            ['id' => 'next_reward', 'header' => 'Próximo premio', 'body' => $card['next_reward']],
        ];

        // Un premio ganado que no se ha cobrado: que el cliente lo vea en su
        // tarjeta (si no, se le olvida y se vence).
        if ($card['pending_reward']) {
            $modules[] = ['id' => 'pending_reward', 'header' => '🎁 Premio por cobrar', 'body' => $card['pending_reward']];
        }

        return [
            'id' => $this->objectId($customer),
            'classId' => $this->classId(),
            // Cliente dado de baja: la tarjeta se ve inactiva en su teléfono.
            'state' => $card['active'] ? 'ACTIVE' : 'INACTIVE',
            'accountId' => (string) $customer->id,
            'accountName' => $card['name'],
            'loyaltyPoints' => [
                'label' => 'Visitas',
                'balance' => ['string' => $card['visits_label']],
            ],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $card['qr'],
            ],
            'textModulesData' => $modules,
        ];
    }

    public function generateSaveLink(Customer $customer): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $serviceAccount = $this->serviceAccount();

        if (empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            Log::error('Google Wallet: faltan client_email/private_key en la cuenta de servicio');

            return null;
        }

        $jwt = JWT::encode([
            'iss' => $serviceAccount['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => [
                'loyaltyObjects' => [$this->buildLoyaltyObjectPayload($customer)],
            ],
        ], $serviceAccount['private_key'], 'RS256');

        return "https://pay.google.com/gp/v/save/{$jwt}";
    }

    /**
     * Crea o actualiza la LoyaltyClass (se corre una vez, vía el comando artisan).
     * Regresa ['success' => bool, 'error' => ?string] para que quien la llame sepa si de verdad funcionó.
     */
    public function upsertClass(): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'error' => 'Google Wallet está deshabilitado o le faltan credenciales'];
        }

        $settings = WalletCardSettings::current();

        $payload = [
            'id' => $this->classId(),
            'issuerName' => $settings->issuer_name,
            'programName' => $settings->program_name,
            'hexBackgroundColor' => $settings->hex_background_color,
            // Se manda en CADA guardado (no solo al crear): un intento
            // anterior llegó a guardar 'approved' del lado de Google antes
            // de que la API rechazara la respuesta, dejando la clase
            // atascada - Google seguía revisando ese valor guardado y
            // rechazando cualquier actualización futura, aunque el código
            // ya no mandara 'approved'. Forzar 'underReview' aquí corrige
            // ese estado atascado.
            'reviewStatus' => 'underReview',
        ];

        if ($logoUrl = $settings->logo_url) {
            $payload['programLogo'] = [
                'sourceUri' => ['uri' => $logoUrl],
                'contentDescription' => [
                    'defaultValue' => ['language' => 'es-MX', 'value' => $settings->program_name],
                ],
            ];
        }

        if ($heroImageUrl = $settings->hero_image_url) {
            $payload['heroImage'] = [
                'sourceUri' => ['uri' => $heroImageUrl],
                'contentDescription' => [
                    'defaultValue' => ['language' => 'es-MX', 'value' => $settings->program_name],
                ],
            ];
        }

        if ($lat = config('services.google_wallet.merchant_lat')) {
            $payload['merchantLocations'] = [[
                'latitude' => (float) $lat,
                'longitude' => (float) config('services.google_wallet.merchant_lng'),
            ]];
        }

        // Siempre se manda (aunque sea vacío) para que borrar el texto de
        // contacto también lo quite de Google, no solo dejarlo de mandar.
        $payload['textModulesData'] = $settings->contact_info
            ? [[
                'id' => 'contact_info',
                'header' => 'Contacto',
                'body' => $settings->contact_info,
            ]]
            : [];

        $token = $this->accessToken();

        if (! $token) {
            return ['success' => false, 'error' => 'No se pudo autenticar con Google'];
        }

        // Con timeout explícito y try/catch: sin esto, si Google se tarda
        // en validar/descargar las imágenes, la petición se queda colgada
        // hasta que PHP la mata de golpe a los 30s con un error fatal que
        // no se puede atrapar - en vez de un mensaje de error normal.
        try {
            $response = Http::withToken($token)->timeout(20)->patch(self::BASE_URL."/loyaltyClass/{$this->classId()}", $payload);

            if ($response->status() === 404) {
                $response = Http::withToken($token)->timeout(20)->post(self::BASE_URL.'/loyaltyClass', $payload);
            }
        } catch (\Throwable $e) {
            Log::error('Google Wallet: excepción al crear/actualizar la clase', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Google tardó demasiado en responder (revisa que las imágenes no sean muy pesadas) o hubo un problema de conexión: '.$e->getMessage()];
        }

        if ($response->failed()) {
            Log::error('Google Wallet: no se pudo crear/actualizar la clase', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'error' => $response->body()];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * Actualiza el pase del cliente en Google. Si el cliente nunca guardó su
     * tarjeta, el objeto no existe todavía en Google — se ignora sin error
     * (cuando la guarde, el enlace ya lleva los datos al día).
     *
     * Errores pasajeros (Google caído, 429, 5xx, sin red) lanzan excepción
     * para que el job se reintente; los permanentes (400/401/403) solo se
     * registran, reintentar no los arregla.
     */
    public function syncCustomerObject(Customer $customer): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->patchObject($this->objectId($customer), $this->buildLoyaltyObjectPayload($customer), ['customer_id' => $customer->id]);
    }

    /** Cliente borrado: su tarjeta queda inactiva (el QR ya no sirve en caja). */
    public function deactivateObject(string $objectId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->patchObject($objectId, ['state' => 'INACTIVE'], ['object_id' => $objectId]);
    }

    protected function patchObject(string $objectId, array $payload, array $context): void
    {
        $token = $this->accessToken();

        if (! $token) {
            throw new \RuntimeException('Google Wallet: no se pudo obtener el token de acceso');
        }

        try {
            $response = Http::withToken($token)->timeout(20)->patch(self::BASE_URL."/loyaltyObject/{$objectId}", $payload);
        } catch (ConnectionException $e) {
            Log::warning('Google Wallet: sin conexión al actualizar el pase, se reintentará', $context + ['error' => $e->getMessage()]);

            throw $e;
        }

        if ($response->status() === 404) {
            return;
        }

        if ($response->status() === 401) {
            // Token vencido o revocado: se pide uno nuevo en el reintento.
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        if ($response->status() === 429 || $response->status() === 401 || $response->serverError()) {
            Log::warning('Google Wallet: error pasajero al actualizar el pase, se reintentará', $context + ['status' => $response->status()]);

            throw new \RuntimeException("Google Wallet respondió {$response->status()}");
        }

        if ($response->failed()) {
            Log::error('Google Wallet: no se pudo actualizar el pase del cliente', $context + [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    public function sendMessageToClass(string $header, string $body, bool $notify): array
    {
        return $this->sendMessage("/loyaltyClass/{$this->classId()}/addMessage", $header, $body, $notify);
    }

    public function sendMessageToObject(Customer $customer, string $header, string $body, bool $notify): array
    {
        return $this->sendMessage("/loyaltyObject/{$this->objectId($customer)}/addMessage", $header, $body, $notify);
    }

    protected function sendMessage(string $path, string $header, string $body, bool $notify): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'quota_exceeded' => false, 'error' => 'Google Wallet está deshabilitado'];
        }

        $token = $this->accessToken();

        if (! $token) {
            return ['success' => false, 'quota_exceeded' => false, 'error' => 'No se pudo autenticar con Google'];
        }

        $message = [
            'header' => $header,
            'body' => $body,
            'id' => (string) Str::uuid(),
            'messageType' => $notify ? 'TEXT_AND_NOTIFY' : 'TEXT',
        ];

        // Se quita solo de la tarjeta a los N días (en el panel, "Mensajes
        // Wallet" conserva el historial completo). 0 = se queda para siempre.
        $startsAt = now();
        $endsAt = null;
        if (($days = (int) config('services.google_wallet.message_days', 7)) > 0) {
            $endsAt = $startsAt->copy()->addDays($days);
            $message['displayInterval'] = [
                'start' => ['date' => $startsAt->copy()->utc()->format('Y-m-d\\TH:i:s\\Z')],
                'end' => ['date' => $endsAt->copy()->utc()->format('Y-m-d\\TH:i:s\\Z')],
            ];
        }

        $payload = ['message' => $message];

        try {
            $response = Http::withToken($token)->timeout(20)->post(self::BASE_URL.$path, $payload);
        } catch (\Throwable $e) {
            Log::error('Google Wallet: excepción al mandar mensaje', ['error' => $e->getMessage()]);

            return ['success' => false, 'quota_exceeded' => false, 'error' => $e->getMessage()];
        }

        if ($response->failed()) {
            // Google no documenta el nombre exacto del campo de error para la cuota excedida;
            // se detecta de forma amplia por status 429 o por el texto de la respuesta.
            $quotaExceeded = $response->status() === 429 || str_contains(strtolower($response->body()), 'quota');

            Log::error('Google Wallet: no se pudo mandar el mensaje', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $error = $response->status() === 404 && str_contains($path, '/loyaltyObject/')
                ? 'Este cliente todavía no ha guardado su tarjeta en Google Wallet.'
                : $response->body();

            return ['success' => false, 'quota_exceeded' => $quotaExceeded, 'error' => $error];
        }

        return ['success' => true, 'quota_exceeded' => false, 'error' => null, 'starts_at' => $startsAt, 'ends_at' => $endsAt];
    }

    protected function classId(): string
    {
        return config('services.google_wallet.issuer_id').'.'.config('services.google_wallet.class_suffix');
    }

    /**
     * Antes era "cliente_{id}", pero el emisor de Google lo comparten
     * producción, staging y el sistema del café: el cliente #5 de un lado
     * chocaba con el #5 del otro y Google entregaba la tarjeta ya existente
     * (con el nombre de otra persona). El UUID del QR es único en todos.
     */
    public function objectId(Customer $customer): string
    {
        return config('services.google_wallet.issuer_id').'.'.config('services.google_wallet.class_suffix').'_'.$customer->ensureQrCode();
    }

    protected function serviceAccount(): array
    {
        return json_decode((string) config('services.google_wallet.service_account_json'), true) ?: [];
    }

    protected function accessToken(): ?string
    {
        if ($cached = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $cached;
        }

        try {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $this->serviceAccount());
            $token = $credentials->fetchAuthToken()['access_token'] ?? null;

            if ($token) {
                Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addMinutes(50));
            }

            return $token;
        } catch (\Throwable $e) {
            Log::error('Google Wallet: no se pudo obtener el token de acceso', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
