<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\GoogleWalletService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{private: string, public: string}
     */
    private function fakeServiceAccount(): array
    {
        // En Windows/XAMPP, openssl_pkey_new necesita que se le indique el
        // openssl.cnf explícitamente o falla con "configuration file routines::no such file".
        $opensslConfig = collect([
            getenv('OPENSSL_CONF'),
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
        ])->filter(fn (?string $path) => $path && file_exists($path))->first();

        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        if ($opensslConfig) {
            $options['config'] = $opensslConfig;
        }

        $resource = openssl_pkey_new($options);
        openssl_pkey_export($resource, $privateKey, null, $options);
        $publicKey = openssl_pkey_get_details($resource)['key'];

        config([
            'services.google_wallet.enabled' => true,
            'services.google_wallet.issuer_id' => '3388000000000000000',
            'services.google_wallet.class_suffix' => 'test_class',
            'services.google_wallet.service_account_json' => json_encode([
                'client_email' => 'test@example-project.iam.gserviceaccount.com',
                'private_key' => $privateKey,
            ]),
        ]);

        return ['private' => $privateKey, 'public' => $publicKey];
    }

    public function test_generate_save_link_produce_un_jwt_firmado_con_el_formato_correcto(): void
    {
        $keys = $this->fakeServiceAccount();
        $customer = Customer::create(['name' => 'Test Cliente', 'phone' => '5511112222']);

        $link = app(GoogleWalletService::class)->generateSaveLink($customer);

        $this->assertNotNull($link);
        $this->assertStringStartsWith('https://pay.google.com/gp/v/save/', $link);

        $jwt = substr($link, strlen('https://pay.google.com/gp/v/save/'));
        $decoded = JWT::decode($jwt, new Key($keys['public'], 'RS256'));

        $this->assertSame('test@example-project.iam.gserviceaccount.com', $decoded->iss);
        $this->assertSame('google', $decoded->aud);
        $this->assertSame('savetowallet', $decoded->typ);

        $object = $decoded->payload->loyaltyObjects[0];
        $this->assertSame('3388000000000000000.cliente_'.$customer->id, $object->id);
        $this->assertSame('3388000000000000000.test_class', $object->classId);
        $this->assertSame($customer->qr_code, $object->barcode->value);
        $this->assertSame('QR_CODE', $object->barcode->type);
    }

    public function test_generate_save_link_regresa_null_si_esta_deshabilitado(): void
    {
        config(['services.google_wallet.enabled' => false]);
        $customer = Customer::create(['name' => 'Test', 'phone' => '5511112233']);

        $this->assertNull(app(GoogleWalletService::class)->generateSaveLink($customer));
    }

    public function test_is_enabled_requiere_issuer_id_y_credenciales(): void
    {
        config([
            'services.google_wallet.enabled' => true,
            'services.google_wallet.issuer_id' => null,
            'services.google_wallet.service_account_json' => null,
        ]);

        $this->assertFalse(app(GoogleWalletService::class)->isEnabled());
    }
}
