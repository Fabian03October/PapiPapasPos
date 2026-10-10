<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\ThermalTicketBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dos bugs que encontró el dueño probando la impresora:
 * 1. Las "Notas para cocina" (ej. "sin cebolla") no salían en la comanda
 *    - nunca se guardaban.
 * 2. Sin insumos dados de alta no se podía llegar a contar el dinero en
 *    el corte de caja (el conteo de inventario se quedaba bloqueado).
 */
class KitchenNotesAndCashCloseTest extends TestCase
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
    }

    public function test_la_nota_para_cocina_se_guarda_y_sale_en_la_comanda(): void
    {
        CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);

        $this->actingAs($this->cajero)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'notes' => '  sin cebolla, bien doradas  ']],
            'payment_method' => 'efectivo',
        ])->assertOk();

        $sale = Sale::with('items')->first();
        $this->assertSame('sin cebolla, bien doradas', $sale->items->first()->notes);

        $this->actingAs($this->cajero)->get("/venta/{$sale->id}/comanda")
            ->assertOk()
            ->assertSee('NOTA: sin cebolla, bien doradas');

        $bluetooth = array_column((new ThermalTicketBuilder)->forKitchen($sale), 'text');
        $this->assertContains('  NOTA: sin cebolla, bien', $bluetooth);
        $this->assertContains('  doradas', $bluetooth);

        $this->actingAs($this->cajero)->get('/venta/historial')->assertSee('sin cebolla, bien doradas');
    }

    public function test_sin_nota_no_aparece_nada_extra(): void
    {
        CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);

        $this->actingAs($this->cajero)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'notes' => '   ']],
            'payment_method' => 'efectivo',
        ])->assertOk();

        $sale = Sale::with('items')->first();
        $this->assertNull($sale->items->first()->notes);
        $this->actingAs($this->cajero)->get("/venta/{$sale->id}/comanda")->assertDontSee('NOTA:');
    }

    public function test_sin_insumos_el_corte_pasa_directo_a_contar_el_dinero(): void
    {
        $session = CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 500, 'opened_at' => now()]);

        $this->actingAs($this->cajero)->get('/caja/cerrar/inventario')->assertRedirect('/caja/cerrar');
        $this->actingAs($this->cajero)->get('/caja/cerrar')->assertOk();

        $this->actingAs($this->cajero)->postJson('/caja/cerrar', [
            'denominations' => [['value' => 500, 'qty' => 1]],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull($session->fresh()->closed_at);
    }

    public function test_con_insumos_se_sigue_pidiendo_el_conteo(): void
    {
        CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 500, 'opened_at' => now()]);
        Ingredient::create(['name' => 'Papa', 'unit' => 'g', 'stock_qty' => 1000, 'min_stock' => 0, 'cost_per_unit' => 0.05, 'is_active' => true]);

        $this->actingAs($this->cajero)->get('/caja/cerrar/inventario')->assertOk()->assertSee('Papa');
        $this->actingAs($this->cajero)->get('/caja/cerrar')->assertRedirect('/caja/cerrar/inventario');
    }
}
