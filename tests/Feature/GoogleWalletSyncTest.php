<?php

namespace Tests\Feature;

use App\Jobs\DeactivateGoogleWalletObjectJob;
use App\Jobs\SyncGoogleWalletObjectJob;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyLevel;
use App\Services\GoogleWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Actualización de la tarjeta en Google Wallet (sin llamar a Google de verdad). */
class GoogleWalletSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_wallet.enabled' => true,
            'services.google_wallet.issuer_id' => '3388000000000000000',
            'services.google_wallet.class_suffix' => 'test_class',
            'services.google_wallet.service_account_json' => json_encode(['client_email' => 'x@y.iam.gserviceaccount.com', 'private_key' => 'x']),
        ]);
        // Token ya "en caché": no se pide a Google.
        Cache::put('google_wallet_access_token', 'token-de-prueba', 60);

        // Nunca llamar a Google de verdad desde las pruebas.
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function wallet(): GoogleWalletService
    {
        return app(GoogleWalletService::class);
    }

    public function test_si_el_cliente_no_ha_guardado_su_tarjeta_no_es_error(): void
    {
        Http::fake(['walletobjects.googleapis.com/*' => Http::response(['error' => ['code' => 404]], 404)]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110000']);

        $this->wallet()->syncCustomerObject($customer);

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/loyaltyObject/3388000000000000000.test_class_'.$customer->qr_code));
    }

    public function test_si_google_falla_se_lanza_para_que_el_job_reintente(): void
    {
        Http::fake(['walletobjects.googleapis.com/*' => Http::response('caído', 503)]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110001']);

        $this->expectException(\RuntimeException::class);
        $this->wallet()->syncCustomerObject($customer);
    }

    public function test_un_error_permanente_no_se_reintenta(): void
    {
        Http::fake(['walletobjects.googleapis.com/*' => Http::response('mal', 400)]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110002']);

        $this->wallet()->syncCustomerObject($customer);

        $this->assertTrue(true); // no lanzó
    }

    public function test_el_job_reintenta_con_espera(): void
    {
        $job = new SyncGoogleWalletObjectJob(Customer::create(['name' => 'Ana', 'phone' => '5511110003']));

        $this->assertSame(5, $job->tries);
        $this->assertNotEmpty($job->backoff());
    }

    public function test_la_tarjeta_muestra_premio_por_cobrar_y_textos_correctos(): void
    {
        $level = LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 10, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110004', 'current_level' => 1, 'current_visits' => 3]);
        CustomerLoyaltyRedemption::create([
            'customer_id' => $customer->id, 'loyalty_level_id' => $level->id, 'type' => 'discount', 'status' => 'pending',
            'earned_at' => now(), 'expires_at' => now()->addDays(7),
        ]);

        $payload = $this->wallet()->buildLoyaltyObjectPayload($customer->fresh());
        $modules = collect($payload['textModulesData'])->keyBy('id');

        $this->assertSame('ACTIVE', $payload['state']);
        $this->assertSame('3 de 8', $payload['loyaltyPoints']['balance']['string']);
        $this->assertStringContainsString('falta 1 visita', $modules['next_reward']['body']);
        $this->assertStringContainsString('10.00% de descuento · vence el', $modules['pending_reward']['body']);
        $this->assertMatchesRegularExpression('/^[a-z]+ \d{4}$/u', $modules['member_since']['body']); // "octubre 2026"
        $this->assertStringNotContainsString('October', $modules['member_since']['body']);
    }

    public function test_cliente_dado_de_baja_tiene_tarjeta_inactiva(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110005', 'is_active' => false]);

        $this->assertSame('INACTIVE', $this->wallet()->buildLoyaltyObjectPayload($customer->fresh())['state']);
    }

    public function test_cambios_que_se_ven_en_la_tarjeta_la_actualizan(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110006']);

        $customer->update(['phone' => '5511110007']); // no se ve en la tarjeta
        Queue::assertNothingPushed();

        $customer->update(['current_visits' => 1]);
        Queue::assertPushed(SyncGoogleWalletObjectJob::class, 1);

        // Ya hay uno en cola para este cliente: no se encola otro (el que
        // está en cola, al correr, manda el dato más reciente).
        $customer->update(['is_active' => false]);
        Queue::assertPushed(SyncGoogleWalletObjectJob::class, 1);

        // Ya que se procesó (se suelta el candado), un cambio nuevo sí encola.
        Cache::lock('laravel_unique_job:'.SyncGoogleWalletObjectJob::class.':'.$customer->id)->forceRelease();
        $customer->update(['name' => 'Ana María']);
        Queue::assertPushed(SyncGoogleWalletObjectJob::class, 2);
    }

    public function test_ganar_un_premio_actualiza_la_tarjeta(): void
    {
        $level = LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 10, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110008']);

        CustomerLoyaltyRedemption::create([
            'customer_id' => $customer->id, 'loyalty_level_id' => $level->id, 'type' => 'gift', 'status' => 'pending',
            'earned_at' => now(), 'expires_at' => now()->addDays(7),
        ]);

        Queue::assertPushed(SyncGoogleWalletObjectJob::class, fn ($job) => $job->customer->is($customer));
    }

    public function test_borrar_al_cliente_desactiva_su_tarjeta(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110009']);
        $objectId = '3388000000000000000.test_class_'.$customer->qr_code;

        $customer->delete();

        Queue::assertPushed(DeactivateGoogleWalletObjectJob::class, fn ($job) => $job->objectId === $objectId);
    }

    public function test_con_wallet_apagado_no_se_encola_nada(): void
    {
        config(['services.google_wallet.enabled' => false]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110010']);

        $customer->update(['current_visits' => 2]);

        Queue::assertNothingPushed();
    }

    public function test_mensaje_a_cliente_sin_tarjeta_dice_por_que_fallo(): void
    {
        Http::fake(['walletobjects.googleapis.com/*' => Http::response('no existe', 404)]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110011']);

        $result = $this->wallet()->sendMessageToObject($customer, 'Hola', 'Prueba', false);

        $this->assertFalse($result['success']);
        $this->assertSame('Este cliente todavía no ha guardado su tarjeta en Google Wallet.', $result['error']);
    }

    public function test_el_encargado_ve_un_qr_para_el_cliente_y_no_el_enlace_de_guardado(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511110012']);

        $html = view('filament.wallet-card-qr', ['customer' => $customer, 'url' => route('registro.confirmacion', $customer->qr_code)])->render();

        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringNotContainsString('pay.google.com', $html);
    }
}
