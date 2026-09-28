<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WalletCardSettings;
use Firebase\JWT\JWT;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleWalletService
{
    private const SCOPE = 'https://www.googleapis.com/auth/wallet_object.issuer';

    private const BASE_URL = 'https://walletobjects.googleapis.com/walletobjects/v1';

    public function __construct(protected LoyaltyService $loyalty)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.google_wallet.enabled')
            && filled(config('services.google_wallet.issuer_id'))
            && filled(config('services.google_wallet.service_account_json'));
    }

    public function buildLoyaltyObjectPayload(Customer $customer): array
    {
        $reward = $this->loyalty->nextRewardSummary($customer);

        return [
            'id' => $this->objectId($customer),
            'classId' => $this->classId(),
            'state' => 'ACTIVE',
            'accountId' => (string) $customer->id,
            'accountName' => $customer->name,
            'loyaltyPoints' => [
                'label' => 'Visitas',
                'balance' => ['string' => "{$customer->current_visits} de 8"],
            ],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $customer->qr_code,
            ],
            'textModulesData' => [
                [
                    'id' => 'member_since',
                    'header' => 'Miembro desde',
                    'body' => $customer->created_at->translatedFormat('F Y'),
                ],
                [
                    'id' => 'next_reward',
                    'header' => 'Próximo premio',
                    'body' => "{$reward['label']} · faltan {$reward['visits_remaining']} visitas",
                ],
            ],
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
            // Google rechaza 'approved' desde la API para cuentas nuevas
            // (probado en vivo: 400 "Invalid review status APPROVED. Use
            // UNDER_REVIEW instead."). El watermark "[SOLO PARA PRUEBAS]"
            // solo se quita cuando Google aprueba la cuenta de negocio a
            // traves de su propio proceso de revision, no por API.
            'reviewStatus' => 'underReview',
            'hexBackgroundColor' => $settings->hex_background_color,
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

        $token = $this->accessToken();

        if (! $token) {
            return ['success' => false, 'error' => 'No se pudo autenticar con Google'];
        }

        $response = Http::withToken($token)->patch(self::BASE_URL."/loyaltyClass/{$this->classId()}", $payload);

        if ($response->status() === 404) {
            $response = Http::withToken($token)->post(self::BASE_URL.'/loyaltyClass', $payload);
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
     * tarjeta, el objeto no existe todavía en Google — se ignora sin error.
     */
    public function syncCustomerObject(Customer $customer): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $token = $this->accessToken();

        if (! $token) {
            return;
        }

        $response = Http::withToken($token)->patch(
            self::BASE_URL."/loyaltyObject/{$this->objectId($customer)}",
            $this->buildLoyaltyObjectPayload($customer)
        );

        if ($response->status() === 404) {
            return;
        }

        if ($response->failed()) {
            Log::error('Google Wallet: no se pudo actualizar el pase del cliente', [
                'customer_id' => $customer->id,
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

        $payload = [
            'message' => [
                'header' => $header,
                'body' => $body,
                'id' => (string) Str::uuid(),
                'messageType' => $notify ? 'TEXT_AND_NOTIFY' : 'TEXT',
            ],
        ];

        try {
            $response = Http::withToken($token)->post(self::BASE_URL.$path, $payload);
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

            return ['success' => false, 'quota_exceeded' => $quotaExceeded, 'error' => $response->body()];
        }

        return ['success' => true, 'quota_exceeded' => false, 'error' => null];
    }

    protected function classId(): string
    {
        return config('services.google_wallet.issuer_id').'.'.config('services.google_wallet.class_suffix');
    }

    protected function objectId(Customer $customer): string
    {
        return config('services.google_wallet.issuer_id').'.cliente_'.$customer->id;
    }

    protected function serviceAccount(): array
    {
        return json_decode((string) config('services.google_wallet.service_account_json'), true) ?: [];
    }

    protected function accessToken(): ?string
    {
        try {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $this->serviceAccount());

            return $credentials->fetchAuthToken()['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Google Wallet: no se pudo obtener el token de acceso', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
