<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\ModifierRecipeItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleRevertService;
use App\Services\ThermalTicketBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Extras, aderezos y salsas repetidos ("Queso ×2") y ticket con solo lo que se cobra. */
class RepeatedModifiersTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private Product $papas;

    private Ingredient $quesoInsumo;

    /** @var array<string, Modifier> */
    private array $mod = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = User::create(['name' => 'Cajero', 'email' => 'c@test.local', 'password' => 'x', 'pin' => '1111',
            'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true]);
        CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 500, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $this->papas = Product::create(['category_id' => $category->id, 'name' => 'Papis', 'base_price' => 60, 'is_active' => true]);

        $presentacion = ModifierGroup::create(['name' => 'Presentación', 'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'sort_order' => 1, 'is_active' => true]);
        $aderezos = ModifierGroup::create(['name' => 'Aderezos', 'min_select' => 0, 'max_select' => 2, 'is_required' => false, 'sort_order' => 2, 'is_active' => true]);
        $extras = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 2, 'is_required' => false, 'sort_order' => 3, 'is_active' => true]);
        $this->papas->modifierGroups()->attach([$presentacion->id, $aderezos->id, $extras->id]);

        foreach (['charola' => [$presentacion, 'Charola', 0], 'queso' => [$aderezos, 'Queso', 0], 'ketchup' => [$aderezos, 'Kétchup', 0],
            'mayo' => [$aderezos, 'Mayonesa', 0], 'extraQueso' => [$extras, 'Extra queso', 15]] as $k => [$g, $name, $price]) {
            $this->mod[$k] = Modifier::create(['modifier_group_id' => $g->id, 'name' => $name, 'price_delta' => $price, 'is_active' => true]);
        }

        $this->quesoInsumo = Ingredient::create(['name' => 'Queso amarillo', 'unit' => 'g', 'stock_qty' => 1000, 'min_stock' => 0, 'cost_per_unit' => 0.1]);
        ModifierRecipeItem::create(['modifier_id' => $this->mod['extraQueso']->id, 'ingredient_id' => $this->quesoInsumo->id, 'qty' => 30]);
    }

    private function cobrar(array $mods, int $qty = 1, string $notes = '')
    {
        return $this->actingAs($this->cajero)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $this->papas->id, 'qty' => $qty,
                'modifier_ids' => array_map(fn ($k) => $this->mod[$k]->id, $mods), 'notes' => $notes]],
            'payment_method' => 'efectivo',
        ]);
    }

    public function test_un_extra_repetido_se_cobra_y_descuenta_inventario_por_cada_vez(): void
    {
        // 2 papas, cada una con Extra queso ×3.
        $this->cobrar(['charola', 'extraQueso', 'extraQueso', 'extraQueso'], qty: 2)->assertOk();
        $sale = Sale::latest('id')->first();
        $item = $sale->items->first();

        $this->assertSame('105.00', (string) $item->unit_price);   // 60 + 15×3
        $this->assertSame('210.00', (string) $sale->total);         // × 2 papas
        $this->assertSame(3, $item->modifiers->firstWhere('modifier_id', $this->mod['extraQueso']->id)->qty);
        $this->assertEquals(1000 - 30 * 3 * 2, $this->quesoInsumo->fresh()->stock_qty); // 180 g
    }

    public function test_las_repeticiones_no_cuentan_para_el_tope_del_grupo(): void
    {
        // Aderezos "hasta 2": Queso ×3 + Kétchup = 2 distintos → pasa.
        $this->cobrar(['charola', 'queso', 'queso', 'queso', 'ketchup'])->assertOk();

        // 3 distintos → no pasa.
        $this->cobrar(['charola', 'queso', 'ketchup', 'mayo'])->assertStatus(422);
    }

    public function test_la_presentacion_no_se_repite(): void
    {
        $this->cobrar(['charola', 'charola'])->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_comanda_dice_por_x2_y_el_ticket_solo_lo_que_se_cobra(): void
    {
        $this->cobrar(['charola', 'queso', 'queso', 'extraQueso', 'extraQueso'], notes: 'sin sal')->assertOk();
        $sale = Sale::latest('id')->first();

        $comanda = array_column((new ThermalTicketBuilder)->forKitchen($sale), 'text');
        $this->assertContains('  + Charola', $comanda);
        $this->assertContains('  + Queso ×2', $comanda);
        $this->assertContains('  + EXTRA Extra queso ×2', $comanda);

        // Ticket: el producto ($60) y el extra de pago ($30); suman $90.
        $ticket = array_column((new ThermalTicketBuilder)->forSale($sale), 'text');
        $this->assertTrue(collect($ticket)->contains(fn ($t) => str_starts_with($t, '1x Papis') && str_ends_with($t, '$60.00')));
        $this->assertTrue(collect($ticket)->contains(fn ($t) => str_starts_with($t, ' + Extra queso ×2') && str_ends_with($t, '$30.00')));
        $todo = implode("\n", $ticket);
        $this->assertStringNotContainsString('Charola', $todo);   // gratis: solo comanda
        $this->assertStringNotContainsString('Queso ×2', $todo);  // aderezo gratis
        $this->assertStringNotContainsString('sin sal', $todo);   // nota: solo comanda

        $this->actingAs($this->cajero)->get("/venta/{$sale->id}/ticket")
            ->assertSee('Extra queso ×2')->assertDontSee('Charola')->assertDontSee('sin sal');
    }

    public function test_al_deshacer_la_orden_regresa_con_sus_repeticiones(): void
    {
        $this->cobrar(['charola', 'extraQueso', 'extraQueso'])->assertOk();
        $sale = Sale::latest('id')->first();

        $order = app(SaleRevertService::class)->revert($sale, $this->cajero, 'Error de captura');
        $item = $order['items'][0];

        $this->assertSame(2, collect($item['modifierIds'])->filter(fn ($id) => $id === $this->mod['extraQueso']->id)->count());
        $this->assertContains('Extra queso ×2', $item['modifierNames']);
        $this->assertEquals(1000, $this->quesoInsumo->fresh()->stock_qty); // el inventario regresó completo
    }
}
