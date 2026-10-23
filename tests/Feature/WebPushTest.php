<?php

namespace Tests\Feature;

use App\Jobs\SendWalletMessageJob;
use App\Jobs\SendWebPushJob;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyLevel;
use App\Models\PushSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use App\Services\WebPushService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Mockery;
use Tests\TestCase;

/** Avisos de "Mi tarjeta" (Web Push), sin mandar nada de verdad. */
class WebPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.webpush.public_key' => 'BPublicaDePrueba',
            'services.webpush.private_key' => 'privadaDePrueba',
            'services.google_wallet.enabled' => false,
        ]);
    }

    private function suscripcion(Customer $customer, string $endpoint = 'https://push.example.com/abc'): PushSubscription
    {
        return PushSubscription::create([
            'customer_id' => $customer->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => 'p256', 'auth_token' => 'auth',
        ]);
    }

    public function test_el_celular_activa_y_desactiva_sus_avisos(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5540000001']);
        $url = "/mi-tarjeta/{$ana->qr_code}/avisos";
        $sub = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'keys' => ['p256dh' => 'BKey', 'auth' => 'secreto']];

        $this->postJson($url, ['endpoint' => 'no-es-url'])->assertStatus(422);
        $this->postJson($url, $sub)->assertCreated();
        $this->postJson($url, $sub)->assertCreated(); // repetir no duplica
        $this->assertSame(1, $ana->pushSubscriptions()->count());

        // Ese celular ahora abre la tarjeta de otro cliente: se mueve.
        $otro = Customer::create(['name' => 'Otro', 'phone' => '5540000002']);
        $this->postJson("/mi-tarjeta/{$otro->qr_code}/avisos", $sub)->assertCreated();
        $this->assertSame(0, $ana->pushSubscriptions()->count());
        $this->assertSame(1, $otro->pushSubscriptions()->count());

        $this->deleteJson("/mi-tarjeta/{$otro->qr_code}/avisos", ['endpoint' => $sub['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_la_tarjeta_ofrece_avisos_solo_si_estan_configurados(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5540000003']);

        $this->get("/mi-tarjeta/{$ana->qr_code}")->assertSee('Activar avisos')->assertSee('data-key="BPublicaDePrueba"', false);

        config(['services.webpush.public_key' => null]);
        $this->get("/mi-tarjeta/{$ana->qr_code}")->assertDontSee('Activar avisos');
    }

    public function test_manda_a_cada_celular_y_borra_los_que_ya_no_existen(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5540000004']);
        $vivo = $this->suscripcion($ana, 'https://push.example.com/vivo');
        $muerto = $this->suscripcion($ana, 'https://push.example.com/muerto');

        $client = Mockery::mock(WebPush::class);
        $client->shouldReceive('queueNotification')->twice()->withArgs(function ($subscription, $payload) use ($ana) {
            $data = json_decode($payload, true);

            return $data['title'] === '🎉 ¡Ganaste tu premio!' && $data['url'] === "/mi-tarjeta/{$ana->qr_code}";
        });
        $client->shouldReceive('flush')->once()->andReturnUsing(function () {
            yield new MessageSentReport(new Request('POST', 'https://push.example.com/vivo'), new Response(201), true);
            yield new MessageSentReport(new Request('POST', 'https://push.example.com/muerto'), new Response(410), false, 'Gone');
        });
        $service = Mockery::mock(WebPushService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('client')->andReturn($client);

        $this->assertSame(1, $service->sendToCustomer($ana, '🎉 ¡Ganaste tu premio!', 'Texto'));
        $this->assertNotNull($vivo->fresh()->last_sent_at);
        $this->assertNull($muerto->fresh()); // desinstaló o quitó el permiso
    }

    public function test_ganar_un_premio_por_cobrar_avisa(): void
    {
        Queue::fake();
        $level = LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 10, 'discount_description' => '10% de descuento', 'is_active' => true]);
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5540000005']);

        CustomerLoyaltyRedemption::create(['customer_id' => $ana->id, 'loyalty_level_id' => $level->id, 'type' => 'discount',
            'status' => 'pending', 'earned_at' => now(), 'expires_at' => now()->addDays(7)]);
        // Cobrado en el momento: no hace falta avisar.
        CustomerLoyaltyRedemption::create(['customer_id' => $ana->id, 'loyalty_level_id' => $level->id, 'type' => 'discount',
            'status' => 'redeemed', 'earned_at' => now(), 'expires_at' => now()->addDays(7), 'redeemed_at' => now()]);

        Queue::assertPushed(SendWebPushJob::class, 1);
        Queue::assertPushed(SendWebPushJob::class, fn ($job) => $job->customerId === $ana->id
            && $job->title === '🎉 ¡Ganaste tu premio!' && str_starts_with($job->body, '10% de descuento. Cóbralo'));
    }

    public function test_avisos_diarios_premio_que_vence_manana_y_cumpleanos(): void
    {
        Queue::fake();
        $level = LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 10, 'is_active' => true]);
        $vence = Customer::create(['name' => 'Vence Mañana', 'phone' => '5540000006']);
        $todavia = Customer::create(['name' => 'Aún No', 'phone' => '5540000007']);
        $cumple = Customer::create(['name' => 'Cumple Hoy', 'phone' => '5540000008', 'birthdate' => now()->subYears(25)]);
        $sinAvisos = Customer::create(['name' => 'Sin Avisos', 'phone' => '5540000009', 'birthdate' => now()->subYears(30)]);
        foreach ([$vence, $todavia, $cumple] as $i => $c) {
            $this->suscripcion($c, "https://push.example.com/{$i}");
        }
        foreach ([[$vence, 1], [$todavia, 3]] as [$c, $days]) {
            CustomerLoyaltyRedemption::create(['customer_id' => $c->id, 'loyalty_level_id' => $level->id, 'type' => 'discount',
                'status' => 'pending', 'earned_at' => now(), 'expires_at' => now()->addDays($days)]);
        }
        Queue::fake(); // ignora los avisos de "ganaste" de arriba

        $this->artisan('fidelidad:avisos-diarios')->assertSuccessful();

        Queue::assertPushed(SendWebPushJob::class, 2);
        Queue::assertPushed(SendWebPushJob::class, fn ($job) => $job->customerId === $vence->id && $job->title === '⏰ Tu premio vence mañana');
        Queue::assertPushed(SendWebPushJob::class, fn ($job) => $job->customerId === $cumple->id && $job->title === '🎂 ¡Feliz cumpleaños, Cumple!');
    }

    public function test_un_mensaje_del_panel_con_notificacion_tambien_suena_en_mi_tarjeta(): void
    {
        Queue::fake();
        $user = User::create(['name' => 'G', 'email' => 'g@test.local', 'password' => 'x', 'pin' => '9999',
            'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true]);
        $message = WalletMessage::create(['title' => '2x1 hoy', 'body' => 'Solo hoy', 'audience' => 'all',
            'notify' => true, 'sent_by_user_id' => $user->id, 'status' => 'queued']);

        (new SendWalletMessageJob($message))->handle(app(GoogleWalletService::class));

        // Google Wallet apagado no es un fallo: sale en "Mi tarjeta" y suena.
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->ends_at);
        Queue::assertPushed(SendWebPushJob::class, fn ($job) => $job->customerId === null && $job->title === '2x1 hoy');
    }
}
