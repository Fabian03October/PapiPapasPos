<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyLevel;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Mi tarjeta": la página/app del cliente. */
class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    private function mensaje(array $attrs): WalletMessage
    {
        $user = User::firstOrCreate(['email' => 'g@test.local'], ['name' => 'Gerente', 'password' => 'x', 'pin' => '9999',
            'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true]);

        return WalletMessage::create([...['body' => 'Detalle', 'audience' => 'all', 'notify' => false,
            'sent_by_user_id' => $user->id, 'status' => 'sent'], ...$attrs]);
    }

    public function test_muestra_qr_sellos_y_proximo_premio(): void
    {
        $customer = Customer::create(['name' => 'Ana López', 'phone' => '5530000001', 'current_visits' => 3]);

        $response = $this->get("/mi-tarjeta/{$customer->qr_code}")
            ->assertOk()
            ->assertSee('Ana López')
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('falta 1 visita')
            ->assertSee('/mi-tarjeta-sw.js', false)
            ->assertSee(route('mi-tarjeta.manifest', $customer->qr_code), false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(3, substr_count($response->getContent(), '🍟'));
    }

    public function test_premio_por_cobrar_y_solo_promociones_vigentes(): void
    {
        $level = LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 10, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5530000002', 'current_level' => 1, 'current_visits' => 4]);
        $otro = Customer::create(['name' => 'Otro', 'phone' => '5530000003']);
        CustomerLoyaltyRedemption::create(['customer_id' => $customer->id, 'loyalty_level_id' => $level->id, 'type' => 'discount',
            'status' => 'pending', 'earned_at' => now(), 'expires_at' => now()->addDays(7)]);

        $this->mensaje(['title' => '2x1 vigente', 'ends_at' => now()->addDays(3)]);
        $this->mensaje(['title' => 'Promo vencida', 'ends_at' => now()->subDay()]);
        $this->mensaje(['title' => 'Solo para Ana', 'audience' => 'customer', 'customer_id' => $customer->id, 'ends_at' => now()->addDay()]);
        $this->mensaje(['title' => 'Solo para Otro', 'audience' => 'customer', 'customer_id' => $otro->id, 'ends_at' => now()->addDay()]);
        $this->mensaje(['title' => 'No se envió', 'status' => 'failed', 'ends_at' => now()->addDay()]);

        $this->get("/mi-tarjeta/{$customer->qr_code}")
            ->assertOk()
            ->assertSee('Premio por cobrar')
            ->assertSee('10.00% de descuento')
            ->assertSee('2x1 vigente')
            ->assertSee('Solo para Ana')
            ->assertDontSee('Promo vencida')
            ->assertDontSee('Solo para Otro')
            ->assertDontSee('No se envió');
    }

    public function test_cliente_dado_de_baja_ve_su_tarjeta_inactiva_y_codigo_inexistente_da_404(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5530000004', 'is_active' => false]);

        $this->get("/mi-tarjeta/{$customer->qr_code}")->assertOk()->assertSee('Esta tarjeta está inactiva');
        $this->get('/mi-tarjeta/no-existe')->assertNotFound();
    }

    public function test_datos_en_json_para_refrescar_y_para_una_futura_app(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5530000005', 'current_visits' => 2]);

        $this->getJson("/mi-tarjeta/{$customer->qr_code}/datos")
            ->assertOk()
            ->assertJsonPath('name', 'Ana')
            ->assertJsonPath('qr', $customer->qr_code)
            ->assertJsonPath('visits', 2)
            ->assertJsonCount(8, 'stamps')
            ->assertJsonPath('stamps.3.reward', 'discount')
            ->assertJsonPath('stamps.7.reward', 'gift')
            ->assertJsonStructure(['next_reward', 'pending_reward', 'promotions', 'generated_at']);
    }

    public function test_al_instalarla_abre_la_tarjeta_de_ese_cliente(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5530000006']);

        $this->get("/mi-tarjeta/{$customer->qr_code}/manifest.webmanifest")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('start_url', "/mi-tarjeta/{$customer->qr_code}?app=1")
            ->assertJsonPath('scope', "/mi-tarjeta/{$customer->qr_code}")
            ->assertJsonPath('display', 'standalone');

        foreach (['icon-192.png', 'icon-512.png', 'icon-512-maskable.png', 'apple-touch-icon.png', 'logo-header.png'] as $icon) {
            $this->assertFileExists(public_path("pwa/{$icon}"));
        }
    }

    public function test_el_registro_lleva_a_su_tarjeta_y_el_enlace_viejo_tambien(): void
    {
        $this->post('/registro', ['name' => 'Nueva', 'phone' => '5530000007'])
            ->assertRedirect(route('mi-tarjeta', ['qrCode' => Customer::where('phone', '5530000007')->value('qr_code'), 'nuevo' => 1]));

        $customer = Customer::where('phone', '5530000007')->first();
        $this->get("/mi-tarjeta/{$customer->qr_code}?nuevo=1")->assertSee('¡Bienvenido, Nueva!');

        $this->get("/registro/confirmacion/{$customer->qr_code}")
            ->assertStatus(301)
            ->assertRedirect(route('mi-tarjeta', $customer->qr_code));
    }

    public function test_el_modo_sin_internet_solo_controla_mi_tarjeta(): void
    {
        $sw = file_get_contents(public_path('mi-tarjeta-sw.js'));

        // Nunca intercepta pantallas del POS: solo /mi-tarjeta/ y estáticos.
        $this->assertStringContainsString("url.pathname.startsWith('/mi-tarjeta/')", $sw);
        $this->assertStringContainsString('if (!isCard) return;', $sw);
        $this->assertStringContainsString("scope: '/mi-tarjeta/'", file_get_contents(resource_path('views/mi-tarjeta/show.blade.php')));
    }
}
