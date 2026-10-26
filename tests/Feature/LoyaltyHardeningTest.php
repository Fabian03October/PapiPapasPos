<?php

namespace Tests\Feature;

use App\Http\Middleware\WorkerWatchdog;
use App\Jobs\SendWebPushJob;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyLevel;
use App\Models\PushSubscription;
use App\Models\WalletCardSettings;
use App\Services\Portal\CardDesign;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Paquete 1: la tarjeta aguanta carga y avisa cuando algo se rompe. */
class LoyaltyHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function subs(Customer $customer, int $n, string $prefix = 'a'): void
    {
        foreach (range(1, $n) as $i) {
            $endpoint = "https://push.example.com/{$prefix}{$i}";
            PushSubscription::create(['customer_id' => $customer->id, 'endpoint' => $endpoint,
                'endpoint_hash' => PushSubscription::hashEndpoint($endpoint), 'public_key' => 'p', 'auth_token' => 'a']);
        }
    }

    public function test_abrir_la_tarjeta_no_crea_sesion_ni_cookies(): void
    {
        config(['session.driver' => 'database']);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5560000001']);

        $response = $this->get("/mi-tarjeta/{$customer->qr_code}")->assertOk();

        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_el_limite_es_por_tarjeta_no_por_ip(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5560000002']);
        $luis = Customer::create(['name' => 'Luis', 'phone' => '5560000003']);

        for ($i = 0; $i < 60; $i++) {
            $this->get("/mi-tarjeta/{$ana->qr_code}/manifest.webmanifest")->assertOk();
        }
        $this->get("/mi-tarjeta/{$ana->qr_code}/manifest.webmanifest")->assertStatus(429);

        // Misma IP, otro cliente (como en una red celular compartida): pasa.
        $this->get("/mi-tarjeta/{$luis->qr_code}/manifest.webmanifest")->assertOk();
    }

    public function test_el_diseno_vive_en_cache_y_se_actualiza_al_guardar(): void
    {
        $this->assertSame('🍟', CardDesign::current()['stamp_emoji']);
        $this->assertTrue(Cache::has(CardDesign::CACHE_KEY));

        WalletCardSettings::current()->update(['stamp_emoji' => '⭐']);

        $this->assertSame('⭐', CardDesign::current()['stamp_emoji']);
    }

    public function test_el_logo_de_siempre_va_en_placa_negra_sobre_fondo_claro(): void
    {
        $this->assertFalse(CardDesign::build(['bg' => '#0a0a0a'])['logo_badge']);
        $this->assertTrue(CardDesign::build(['bg' => '#ffffff'])['logo_badge']);
    }

    public function test_un_mensaje_a_todos_se_reparte_en_tandas(): void
    {
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $activo = Customer::create(['name' => 'Ana', 'phone' => '5560000004']);
        $baja = Customer::create(['name' => 'Baja', 'phone' => '5560000005', 'is_active' => false]);
        $this->subs($activo, 450);
        $this->subs($baja, 5, 'b');

        Queue::fake();
        (new SendWebPushJob(null, '2x1', 'Hoy'))->handle(app(WebPushService::class));

        Queue::assertPushed(SendWebPushJob::class, 3);
        $sizes = Queue::pushed(SendWebPushJob::class)->map(fn ($job) => count($job->subscriptionIds))->sort()->values()->all();
        $this->assertSame([50, 200, 200], $sizes); // los de clientes dados de baja no van
    }

    public function test_los_recordatorios_salen_una_sola_vez_y_se_ponen_al_corriente(): void
    {
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $level = LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 10, 'is_active' => true]);
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5560000006']);
        $this->subs($ana, 1);
        Queue::fake();
        // Vence HOY en la noche (el recordatorio de "mañana" no salió ayer).
        CustomerLoyaltyRedemption::create(['customer_id' => $ana->id, 'loyalty_level_id' => $level->id, 'type' => 'discount',
            'status' => 'pending', 'earned_at' => now()->subDays(6), 'expires_at' => now()->endOfDay()]);
        Queue::fake();

        $this->artisan('fidelidad:avisos-diarios');
        $this->artisan('fidelidad:avisos-diarios'); // corre cada hora

        Queue::assertPushed(SendWebPushJob::class, 1);
    }

    public function test_quien_nacio_el_29_de_febrero_recibe_su_felicitacion_el_28(): void
    {
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->travelTo(now()->setDate(2027, 2, 28)->setTime(12, 0)); // 2027 no es bisiesto
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5560000007', 'birthdate' => '2000-02-29']);
        $this->subs($ana, 1);
        Queue::fake();

        $this->artisan('fidelidad:avisos-diarios');

        Queue::assertPushed(SendWebPushJob::class, fn ($job) => $job->customerId === $ana->id && str_contains($job->title, 'cumpleaños'));
    }

    public function test_el_vigilante_avisa_por_correo_si_el_worker_se_cae_una_vez_cada_2_horas(): void
    {
        config(['services.alertas.email' => 'dueno@papis.test']);
        Mail::fake();
        $watchdog = new WorkerWatchdog;

        // Worker vivo y sin trabajos atorados: nada.
        Cache::put(WorkerWatchdog::HEARTBEAT_KEY, time());
        $this->assertSame([], $watchdog->problems());

        // Sin señal de vida hace 20 min y un trabajo esperando 15 min.
        Cache::put(WorkerWatchdog::HEARTBEAT_KEY, now()->subMinutes(20)->timestamp);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'available_at' => now()->subMinutes(15)->timestamp, 'created_at' => now()->subMinutes(15)->timestamp]);
        $this->assertCount(2, $watchdog->problems());

        $check = fn () => $this->check();
        $check->call($watchdog);
        $check->call($watchdog); // mismo problema: no repite el correo
        Mail::assertSentCount(1);
    }

    public function test_el_worker_da_senal_de_vida_mientras_trabaja(): void
    {
        Cache::forget(WorkerWatchdog::HEARTBEAT_KEY);

        event(new Looping('database', 'default'));

        $this->assertNotNull(Cache::get(WorkerWatchdog::HEARTBEAT_KEY));
    }

    public function test_cambiar_el_enlace_invalida_el_viejo_sin_tocar_sus_sellos(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5560000008', 'current_visits' => 5]);
        $this->subs($ana, 2);
        $viejo = $ana->qr_code;

        $ana->regenerateCardCode();

        $this->assertNotSame($viejo, $ana->fresh()->qr_code);
        $this->get("/mi-tarjeta/{$viejo}")->assertNotFound();
        $this->get("/mi-tarjeta/{$ana->qr_code}")->assertOk();
        $this->assertSame(5, $ana->fresh()->current_visits);
        $this->assertSame(0, $ana->pushSubscriptions()->count());
    }

    public function test_el_qr_se_puede_ver_a_pantalla_completa(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5560000009']);

        $this->get("/mi-tarjeta/{$ana->qr_code}")
            ->assertSee('id="qr-grande"', false)
            ->assertSee('tócalo para agrandarlo');
    }
}
