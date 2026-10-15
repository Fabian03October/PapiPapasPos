<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marco fijo del POS: cambiar de pantalla recargaba la página y Chrome
 * cortaba el Bluetooth de la impresora. Ahora la barra, el menú y la
 * conexión viven en un marco que no se recarga; las pantallas cargan en un
 * iframe dentro de él.
 */
class PosShellFrameTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = User::create([
            'name' => 'Cajero', 'email' => 'cajero@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => Role::firstOrCreate(['name' => 'cajero'])->id, 'is_active' => true,
        ]);
        CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);
    }

    public function test_al_abrir_el_sistema_se_arma_el_marco_con_la_pantalla_en_el_centro(): void
    {
        $response = $this->actingAs($this->cajero)
            ->withHeader('Sec-Fetch-Dest', 'document')
            ->get('/venta?x=1')
            ->assertOk();

        // El marco: barra, menú (que abre las pantallas en el centro) e impresora...
        $response->assertSee('id="contenido"', false)
            ->assertSee('src="' . url('/venta?x=1') . '"', false)
            ->assertSee('id="user-menu-trigger"', false)
            ->assertSee('data-nav="caja"', false)
            ->assertSee('target="contenido"', false)
            ->assertSee('js/impresora-bt.js', false)
            ->assertSee('js/marco.js', false);

        // ...pero no la pantalla en sí, que la trae el iframe.
        $response->assertDontSee('id="product-grid"', false);
    }

    public function test_la_pantalla_del_centro_viene_sola_y_usa_la_impresora_del_marco(): void
    {
        $response = $this->actingAs($this->cajero)
            ->withHeader('Sec-Fetch-Dest', 'iframe')
            ->get('/venta')
            ->assertOk();

        $response->assertSee('id="product-grid"', false)
            ->assertSee('<meta name="pos-active" content="vender">', false)
            ->assertSee('window.ImpresoraBT = Object.assign({}, parent.ImpresoraBT', false)
            ->assertDontSee('id="user-menu-trigger"', false)
            ->assertDontSee('js/impresora-bt.js', false)
            ->assertDontSee('js/marco.js', false);
    }

    public function test_sin_el_dato_del_navegador_la_pagina_sale_completa_como_antes(): void
    {
        $this->actingAs($this->cajero)->get('/venta')
            ->assertOk()
            ->assertSee('id="product-grid"', false)
            ->assertSee('id="user-menu-trigger"', false)
            ->assertDontSee('id="contenido"', false)
            ->assertDontSee('target="contenido"', false);
    }

    public function test_el_panel_admin_se_abre_dentro_del_marco_para_no_cortar_la_impresora(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => 'password',
            'pin' => '9999', 'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true,
        ]);

        $html = $this->actingAs($manager)->withHeader('Sec-Fetch-Dest', 'document')->get('/venta')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<a href="/admin"\s+target="contenido"#', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/prueba-impresora"\s+target="contenido"#', $html);
        $this->assertStringContainsString('id="bt-header-connect"', $html);

        // Sin marco (navegador viejo), el admin abre normal.
        $plain = $this->flushHeaders()->actingAs($manager)->get('/venta')->getContent();
        $this->assertDoesNotMatchRegularExpression('#<a href="/admin"\s+target="contenido"#', $plain);
    }

    public function test_el_admin_trae_su_boton_para_volver_a_ventas(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => 'password',
            'pin' => '9999', 'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true,
        ]);

        // Dentro del marco se esconde la barra del POS: este es el camino de regreso.
        $this->actingAs($manager)->get('/admin')
            ->assertOk()
            ->assertSee('Volver a ventas')
            ->assertSee('href="' . route('venta.index') . '"', false);
    }

    public function test_el_login_se_sale_del_marco_si_la_sesion_se_vencio(): void
    {
        $this->withHeader('Sec-Fetch-Dest', 'iframe')->get('/login')
            ->assertOk()
            ->assertSee('if (window.top !== window.self) window.top.location.href = window.location.href;', false);
    }

    public function test_todas_las_pantallas_del_pos_cargan_dentro_del_marco(): void
    {
        foreach (['/venta', '/venta/historial', '/venta/cliente', '/caja', '/prueba-impresora'] as $url) {
            $response = $this->actingAs($this->cajero)->withHeader('Sec-Fetch-Dest', 'iframe')->get($url);
            $response->assertOk();
            $response->assertSee('name="pos-active"', false);
        }
    }
}
