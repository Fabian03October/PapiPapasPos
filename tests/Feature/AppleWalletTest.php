<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleWalletObjectJob;
use App\Jobs\UpdateAppleWalletPassJob;
use App\Models\AppleWalletPass;
use App\Models\AppleWalletRegistration;
use App\Models\Customer;
use App\Services\AppleWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Apple Wallet: pase, web service de Apple y avisos (sin llamar a Apple de verdad). */
class AppleWalletTest extends TestCase
{
    use RefreshDatabase;

    private const TYPE = 'pass.com.test.papipapas';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.apple_wallet.enabled' => true,
            'services.apple_wallet.pass_type_identifier' => self::TYPE,
            'services.apple_wallet.team_identifier' => 'ABCDE12345',
            'services.apple_wallet.certificate_base64' => base64_encode('no-es-un-certificado'),
            'services.google_wallet.enabled' => false,
        ]);
        Http::preventStrayRequests();
    }

    private function apple(): AppleWalletService
    {
        return app(AppleWalletService::class);
    }

    /** Un pase con un iPhone registrado. */
    private function passWithDevice(Customer $customer): AppleWalletPass
    {
        $pass = $this->apple()->passFor($customer);
        AppleWalletRegistration::create(['apple_wallet_pass_id' => $pass->id, 'device_library_identifier' => 'iphone-1', 'push_token' => 'token-apns']);

        return $pass;
    }

    private function auth(AppleWalletPass $pass): array
    {
        return ['Authorization' => 'ApplePass '.$pass->authentication_token];
    }

    public function test_el_pase_dice_lo_mismo_que_la_tarjeta_de_google(): void
    {
        $customer = Customer::create(['name' => 'Ana López', 'phone' => '5520000001', 'current_visits' => 7]);
        $pass = $this->apple()->passFor($customer);

        $json = $this->apple()->passJson($customer, $pass);

        $this->assertSame(self::TYPE, $json['passTypeIdentifier']);
        $this->assertSame($customer->qr_code, $json['serialNumber']);
        $this->assertSame($customer->qr_code, $json['barcodes'][0]['message']); // lo que escanea la caja
        $this->assertSame('7 de 8', $json['storeCard']['headerFields'][0]['value']);
        $this->assertStringContainsString('falta 1 visita', $json['storeCard']['secondaryFields'][0]['value']);
        $this->assertSame('Ana López', $json['storeCard']['primaryFields'][0]['value']);
        $this->assertFalse($json['voided']);
        $this->assertStringEndsWith('/wallet/apple', $json['webServiceURL']);
    }

    public function test_cliente_dado_de_baja_tiene_el_pase_anulado(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000002', 'is_active' => false]);

        $this->assertTrue($this->apple()->passJson($customer->fresh(), $this->apple()->passFor($customer))['voided']);
    }

    public function test_se_genera_un_pkpass_firmado_con_todo_lo_que_apple_pide(): void
    {
        [$p12, $password] = $this->selfSignedP12();
        config(['services.apple_wallet.certificate_base64' => base64_encode($p12), 'services.apple_wallet.certificate_password' => $password]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000003']);

        $bytes = $this->apple()->generate($customer);

        $file = tempnam(sys_get_temp_dir(), 'pkpass');
        file_put_contents($file, $bytes);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        foreach (['pass.json', 'manifest.json', 'signature', 'icon.png', 'icon@2x.png', 'logo.png'] as $name) {
            $this->assertNotFalse($zip->locateName($name), "Falta {$name} en el .pkpass");
        }
        $pass = json_decode($zip->getFromName('pass.json'), true);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame($customer->qr_code, $pass['serialNumber']);
        $this->assertSame(sha1($zip->getFromName('pass.json')), $manifest['pass.json']);
        $zip->close();
        unlink($file);
    }

    public function test_descarga_solo_con_apple_encendido_y_cliente_activo(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000004']);
        $this->partialMock(AppleWalletService::class, fn ($m) => $m->shouldReceive('generate')->andReturn('PKPASS'));

        $this->get("/wallet/apple/{$customer->qr_code}.pkpass")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.pkpass')
            ->assertContent('PKPASS');

        $this->get('/wallet/apple/no-existe.pkpass')->assertNotFound();

        config(['services.apple_wallet.enabled' => false]);
        $this->get("/wallet/apple/{$customer->qr_code}.pkpass")->assertNotFound();
    }

    public function test_el_iphone_se_registra_solo_con_su_token(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000005']);
        $pass = $this->apple()->passFor($customer);
        $url = '/wallet/apple/v1/devices/iphone-9/registrations/'.self::TYPE.'/'.$pass->serial_number;

        $this->postJson($url, ['pushToken' => 'abc'])->assertUnauthorized();
        $this->postJson($url, ['pushToken' => 'abc'], ['Authorization' => 'ApplePass otro'])->assertUnauthorized();

        $this->postJson($url, ['pushToken' => 'abc'], $this->auth($pass))->assertStatus(201);
        $this->postJson($url, ['pushToken' => 'nuevo'], $this->auth($pass))->assertStatus(200);
        $this->assertSame('nuevo', AppleWalletRegistration::firstOrFail()->push_token);

        $this->deleteJson($url, [], $this->auth($pass))->assertOk();
        $this->assertSame(0, AppleWalletRegistration::count());
    }

    public function test_el_iphone_pregunta_que_pases_cambiaron(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000006']);
        $pass = $this->passWithDevice($customer);
        $url = '/wallet/apple/v1/devices/iphone-1/registrations/'.self::TYPE;

        $this->getJson($url)->assertOk()->assertJsonPath('serialNumbers', [$pass->serial_number]);

        $tag = (string) $pass->fresh()->updated_at->timestamp;
        $this->getJson("{$url}?passesUpdatedSince={$tag}")->assertNoContent();

        $this->travel(5)->seconds();
        $pass->touch();
        $this->getJson("{$url}?passesUpdatedSince={$tag}")->assertOk()->assertJsonPath('serialNumbers', [$pass->serial_number]);

        $this->getJson('/wallet/apple/v1/devices/otro-iphone/registrations/'.self::TYPE)->assertNoContent();
    }

    public function test_pase_al_dia_y_304_si_no_cambio(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000007']);
        $pass = $this->apple()->passFor($customer);
        $this->partialMock(AppleWalletService::class, fn ($m) => $m->shouldReceive('generate')->andReturn('PKPASS'));
        $url = '/wallet/apple/v1/passes/'.self::TYPE.'/'.$pass->serial_number;

        $this->get($url)->assertUnauthorized();
        $this->get($url, $this->auth($pass))->assertOk()->assertContent('PKPASS');
        $this->get($url, $this->auth($pass) + ['If-Modified-Since' => $pass->updated_at->toRfc7231String()])->assertStatus(304);
    }

    public function test_aviso_vacio_por_apns_y_se_borra_el_iphone_que_ya_no_lo_tiene(): void
    {
        Http::fake([
            'api.push.apple.com/3/device/token-apns' => Http::response('', 200),
            'api.push.apple.com/3/device/token-viejo' => Http::response('{"reason":"Unregistered"}', 410),
        ]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000008']);
        $pass = $this->passWithDevice($customer);
        AppleWalletRegistration::create(['apple_wallet_pass_id' => $pass->id, 'device_library_identifier' => 'iphone-2', 'push_token' => 'token-viejo']);

        $this->apple()->pushUpdate($pass->load('registrations'));

        Http::assertSent(fn ($r) => $r->url() === 'https://api.push.apple.com/3/device/token-apns'
            && $r->header('apns-topic') === [self::TYPE] && $r->body() === '{}');
        $this->assertSame(['iphone-1'], AppleWalletRegistration::pluck('device_library_identifier')->all());
    }

    public function test_si_apns_falla_se_lanza_para_reintentar(): void
    {
        Http::fake(['api.push.apple.com/*' => Http::response('', 503)]);
        $pass = $this->passWithDevice(Customer::create(['name' => 'Ana', 'phone' => '5520000009']));

        $this->expectException(\RuntimeException::class);
        $this->apple()->pushUpdate($pass->load('registrations'));
    }

    public function test_sumar_un_sello_avisa_a_apple_solo_si_el_cliente_tiene_pase(): void
    {
        Queue::fake();
        $sinPase = Customer::create(['name' => 'Sin pase', 'phone' => '5520000010']);
        $conPase = Customer::create(['name' => 'Con pase', 'phone' => '5520000011']);
        $this->apple()->passFor($conPase);

        $sinPase->update(['current_visits' => 1]);
        $conPase->update(['current_visits' => 1]);

        Queue::assertPushed(UpdateAppleWalletPassJob::class, 1);
        Queue::assertPushed(UpdateAppleWalletPassJob::class, fn ($job) => $job->customer->is($conPase));
        Queue::assertNotPushed(SyncGoogleWalletObjectJob::class); // Google apagado en esta prueba
    }

    public function test_el_job_marca_el_pase_como_modificado_y_avisa(): void
    {
        Http::fake(['api.push.apple.com/*' => Http::response('', 200)]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5520000012']);
        $pass = $this->passWithDevice($customer);
        $before = $pass->updated_at;

        $this->travel(10)->seconds();
        (new UpdateAppleWalletPassJob($customer))->withFakeQueueInteractions()->handle($this->apple());

        $this->assertTrue($pass->fresh()->updated_at->gt($before));
        Http::assertSentCount(1);
    }

    /** @return array{0: string, 1: string} certificado .p12 autofirmado (solo para probar la firma) */
    private function selfSignedP12(): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($options);
        $csr = openssl_csr_new(['commonName' => 'Pass Type ID: '.self::TYPE], $key, $options);
        $cert = openssl_csr_sign($csr, null, $key, 1, $options);
        openssl_pkcs12_export($cert, $p12, $key, 'secreto');

        return [$p12, 'secreto'];
    }
}
