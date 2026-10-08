<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesHistory;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Descuentos y cortesías que el cajero pone a mano en el carrito (a un
 * producto o a toda la venta). Pedido por el cliente: libres (sin código
 * del manager) pero con motivo obligatorio y desglosados en el historial
 * para poder auditarlos.
 */
class ManualDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private CashSession $cashSession;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'cajero']);
        $this->cajero = User::create([
            'name' => 'Cajero', 'email' => 'cajero@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => $role->id, 'is_active' => true,
        ]);
        $this->cashSession = CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
    }

    private function product(string $name, float $price): Product
    {
        $category = Category::firstOrCreate(['name' => 'Antojitos'], ['sort_order' => 1, 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => $name, 'base_price' => $price, 'is_active' => true]);
    }

    private function cobrar(array $payload)
    {
        return $this->actingAs($this->cajero)->postJson('/venta/cobrar', [
            'payment_method' => 'efectivo',
            ...$payload,
        ]);
    }

    public function test_porcentaje_a_un_producto_queda_guardado_con_su_motivo(): void
    {
        $papas = $this->product('Papas', 100);
        $refresco = $this->product('Refresco', 35);

        $response = $this->cobrar(['items' => [
            ['product_id' => $papas->id, 'qty' => 1, 'discount' => ['type' => 'percent', 'value' => 10, 'reason' => 'Cliente frecuente']],
            ['product_id' => $refresco->id, 'qty' => 1],
        ]]);

        $response->assertOk()->assertJson(['success' => true, 'total' => 125.0]);

        $sale = Sale::with('items')->first();
        $this->assertEquals(135, $sale->subtotal);
        $this->assertEquals(10, $sale->discount);
        $this->assertEquals(10, $sale->manual_discount);
        $this->assertEquals(125, $sale->total);

        $item = $sale->items->firstWhere('product_id', $papas->id);
        $this->assertEquals(10, $item->manual_discount);
        $this->assertSame('percent', $item->manual_discount_type);
        $this->assertSame('Cliente frecuente', $item->manual_discount_reason);
        $this->assertSame('Desc. 10% (Cliente frecuente)', $item->manualDiscountLabel());

        $this->assertSame('manual_producto', $sale->discount_breakdown[0]['kind']);
        $this->assertSame('Papas', $sale->discount_breakdown[0]['product']);
    }

    public function test_cortesia_a_toda_la_venta_cobra_cero_pero_si_descuenta_inventario(): void
    {
        $papas = $this->product('Papas', 89);
        $papa = Ingredient::create(['name' => 'Papa', 'unit' => 'g', 'stock_qty' => 1000, 'min_stock' => 0, 'cost_per_unit' => 0.05, 'is_active' => true]);
        RecipeItem::create(['product_id' => $papas->id, 'ingredient_id' => $papa->id, 'qty' => 200]);

        $this->cobrar([
            'items' => [['product_id' => $papas->id, 'qty' => 2]],
            'sale_discount' => ['type' => 'courtesy', 'reason' => 'Cortesía de la casa'],
        ])->assertOk()->assertJson(['total' => 0.0]);

        $sale = Sale::first();
        $this->assertEquals(178, $sale->manual_discount);
        $this->assertEquals(0, $sale->total);
        $this->assertSame('Cortesía (Cortesía de la casa)', $sale->discount_breakdown[0]['label']);
        $this->assertEquals(600, $papa->fresh()->stock_qty);
    }

    public function test_el_descuento_manual_se_suma_despues_de_las_promociones_sin_dejar_la_venta_en_negativo(): void
    {
        $papas = $this->product('Papas', 100);
        Promotion::create(['name' => 'Papas a mitad', 'type' => 'discount', 'scope' => 'product', 'discount_mode' => 'percent', 'percent_value' => 50, 'is_active' => true])
            ->products()->attach($papas->id, ['qty_required' => 1]);

        // Monto fijo a toda la venta: se aplica sobre lo que queda ($50), no sobre $100.
        $this->cobrar([
            'items' => [['product_id' => $papas->id, 'qty' => 1]],
            'sale_discount' => ['type' => 'amount', 'value' => 80, 'reason' => 'Queja del cliente'],
        ])->assertOk()->assertJson(['total' => 0.0]);

        $sale = Sale::first();
        $this->assertEquals(100, $sale->discount);
        $this->assertEquals(50, $sale->manual_discount);
        $this->assertSame(['promocion', 'manual_venta'], array_column($sale->discount_breakdown, 'kind'));
    }

    public function test_la_vista_previa_regresa_el_mismo_desglose_que_se_guarda(): void
    {
        $papas = $this->product('Papas', 100);

        $this->actingAs($this->cajero)->postJson('/venta/promociones/preview', [
            'items' => [['product_id' => $papas->id, 'qty' => 1, 'discount' => ['type' => 'amount', 'value' => 15, 'reason' => 'Empleado']]],
            'sale_discount' => ['type' => 'percent', 'value' => 10, 'reason' => 'Cliente frecuente'],
        ])
            ->assertOk()
            ->assertJson([
                'subtotal' => 100,
                'manual_discount' => 23.5, // $15 + 10% de los $85 que quedan
                'total' => 76.5,
            ])
            ->assertJsonCount(2, 'discount_breakdown');
    }

    public function test_sin_motivo_no_se_puede_dar_descuento(): void
    {
        $papas = $this->product('Papas', 100);

        $this->cobrar(['items' => [['product_id' => $papas->id, 'qty' => 1, 'discount' => ['type' => 'percent', 'value' => 10]]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.discount.reason');

        $this->cobrar([
            'items' => [['product_id' => $papas->id, 'qty' => 1]],
            'sale_discount' => ['type' => 'amount', 'reason' => 'Sin monto'],
        ])->assertStatus(422)->assertJsonValidationErrors('sale_discount.value');

        $this->assertSame(0, Sale::count());
    }

    public function test_cancelar_un_producto_con_descuento_solo_resta_lo_que_se_cobro(): void
    {
        $papas = $this->product('Papas', 100);
        $refresco = $this->product('Refresco', 35);

        $this->cobrar(['items' => [
            ['product_id' => $papas->id, 'qty' => 1, 'discount' => ['type' => 'courtesy', 'reason' => 'Error en el pedido']],
            ['product_id' => $refresco->id, 'qty' => 1],
        ]])->assertOk();

        $sale = Sale::with('items')->first();
        $this->assertEquals(35, $sale->total);

        // Se cancela la papa que iba de cortesía: el cliente no pagó nada
        // por ella, así que el total cobrado no debe bajar.
        $papaItem = $sale->items->firstWhere('product_id', $papas->id);
        (new SaleIncidentService)->createAndApprove([$papaItem->id], 'cancelacion', 'Se canceló el pedido', $this->cajero, $this->cajero);

        $sale->refresh();
        $this->assertEquals(35, $sale->total);
        $this->assertEquals(35, $sale->subtotal);
        $this->assertEquals(0, $sale->manual_discount);
    }

    public function test_el_desglose_aparece_en_ticket_historial_del_turno_y_admin(): void
    {
        $papas = $this->product('Papas', 100);

        $this->cobrar(['items' => [
            ['product_id' => $papas->id, 'qty' => 1, 'discount' => ['type' => 'amount', 'value' => 20, 'reason' => 'Queja del cliente']],
        ]])->assertOk();

        $sale = Sale::first();

        $this->actingAs($this->cajero)->get("/venta/{$sale->id}/ticket")
            ->assertOk()
            ->assertSee('Desc. $20.00 (Queja del cliente)')
            ->assertSee('Desc. en productos');

        $this->actingAs($this->cajero)->get('/venta/historial')
            ->assertOk()
            ->assertSee('Con descuento')
            ->assertSee('Desc. $20.00 (Queja del cliente)');

        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => 'password',
            'pin' => '9999', 'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(SalesHistory::class)
            ->filterTable('con_descuento_manual', true)
            ->assertCanSeeTableRecords([$sale])
            ->callTableAction('ver', $sale)
            ->assertHasNoTableActionErrors();
    }
}
