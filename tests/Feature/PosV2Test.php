<?php

namespace Tests\Feature;

use App\Models\BusinessSettings;
use App\Models\CashSession;
use App\Models\CashSessionDenomination;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\ThermalTicketBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POS v2: cobro estilo OaxacaWare (transferencia, deshacer/corregir) y los
 * pedidos del cliente (presentación obligatoria y orden de aderezos,
 * comanda, cumpleaños, caja con dos conteos).
 */
class PosV2Test extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private CashSession $session;

    private Product $papas;

    private Product $refresco;

    private array $mod = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = $this->user('cajero', '1111');
        $this->session = CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 500, 'opened_at' => now()]);

        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $this->papas = Product::create(['category_id' => $category->id, 'name' => 'Papas', 'base_price' => 60, 'is_active' => true]);
        $this->refresco = Product::create(['category_id' => $category->id, 'name' => 'Refresco', 'base_price' => 35, 'is_active' => true]);

        $aderezos = ModifierGroup::create(['name' => 'Aderezos', 'min_select' => 0, 'max_select' => 0, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $presentacion = ModifierGroup::create(['name' => 'Presentación', 'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'sort_order' => 5, 'is_active' => true]);
        $extras = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 2, 'is_required' => false, 'sort_order' => 9, 'is_active' => true]);
        $this->papas->modifierGroups()->attach([$aderezos->id, $presentacion->id, $extras->id]);

        foreach ([
            'domo' => [$presentacion, 'Domo', 0, 1], 'mayonesa' => [$aderezos, 'Mayonesa', 0, 2], 'queso' => [$aderezos, 'Queso', 0, 1],
            'salchicha' => [$extras, 'Salchicha', 12, 1], 'tocino' => [$extras, 'Tocino', 15, 2], 'chetos' => [$extras, 'Chetos', 10, 3],
        ] as $key => [$group, $name, $price, $order]) {
            $this->mod[$key] = Modifier::create(['modifier_group_id' => $group->id, 'name' => $name, 'price_delta' => $price, 'sort_order' => $order, 'is_active' => true]);
        }
    }

    private function user(string $role, string $pin): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => "{$role}{$pin}@test.local", 'password' => 'password',
            'pin' => $pin, 'role_id' => Role::firstOrCreate(['name' => $role])->id, 'is_active' => true,
        ]);
    }

    private function cobrar(array $mods, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->cajero)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $this->papas->id, 'qty' => 1, 'modifier_ids' => array_map(fn ($k) => $this->mod[$k]->id, $mods), 'notes' => 'sin sal']],
            'payment_method' => 'efectivo',
            ...$extra,
        ]);
    }

    // --- Presentación obligatoria y orden ---

    public function test_no_se_vende_sin_presentacion_ni_con_mas_extras_de_los_permitidos(): void
    {
        $this->cobrar(['queso'])->assertStatus(422)->assertJsonFragment(['Falta elegir Presentación de "Papas".']);
        $this->cobrar(['domo', 'salchicha', 'tocino', 'chetos'])->assertStatus(422)
            ->assertJsonFragment(['En Extras de "Papas" se pueden elegir máximo 2.']);
        $this->cobrar(['domo', 'queso'])->assertOk();
    }

    public function test_la_pantalla_recibe_la_presentacion_primero_y_los_aderezos_en_su_orden(): void
    {
        $groups = $this->actingAs($this->cajero)->getJson('/venta/productos/'.$this->papas->id)->json('modifier_groups');

        $this->assertSame(['Presentación', 'Aderezos', 'Extras'], array_column($groups, 'name'));
        $this->assertSame(['Queso', 'Mayonesa'], array_column($groups[1]['modifiers'], 'name'));
    }

    public function test_la_comanda_trae_cliente_orden_y_extras_destacados(): void
    {
        $customer = Customer::create(['name' => 'Ana López', 'phone' => '5511112222']);
        $this->cobrar(['mayonesa', 'salchicha', 'domo', 'queso'], ['customer_id' => $customer->id])->assertOk();
        $sale = Sale::latest('id')->first();

        $this->actingAs($this->cajero)->get("/venta/{$sale->id}/comanda")
            ->assertOk()
            ->assertSeeInOrder(['Ana López', '+ Domo', '+ Queso', '+ Mayonesa', 'EXTRA Salchicha', 'NOTA: sin sal']);

        $lines = (new ThermalTicketBuilder)->forKitchen($sale);
        $texts = array_column($lines, 'text');
        $this->assertContains('Ana López', $texts);
        $this->assertSame(['  + Domo', '  + Queso', '  + Mayonesa', '  + EXTRA Salchicha'],
            array_values(array_filter($texts, fn ($t) => str_starts_with($t, '  + '))));
        $extra = collect($lines)->firstWhere('text', '  + EXTRA Salchicha');
        $this->assertTrue($extra['bold'] && ($extra['underline'] ?? false));
    }

    public function test_queso_va_justo_despues_de_la_presentacion_aunque_en_el_catalogo_este_al_final(): void
    {
        $this->mod['queso']->update(['sort_order' => 99]);
        $this->cobrar(['mayonesa', 'queso', 'salchicha', 'domo'])->assertOk();
        $sale = Sale::latest('id')->first();

        $names = $sale->items->first()->orderedModifiers()->map(fn ($m) => $m->modifier->name)->all();

        $this->assertSame(['Domo', 'Queso', 'Mayonesa', 'Salchicha'], $names);
    }

    public function test_todo_ticket_en_efectivo_muestra_recibido_y_cambio_aunque_se_reimprima(): void
    {
        // Pagó con $200 una venta de $72 (papas $60 + salchicha $12).
        $this->cobrar(['domo', 'salchicha'], ['amount_received' => 200])->assertOk();
        $sale = Sale::latest('id')->first();
        $this->assertSame('200.00', (string) $sale->amount_received);

        // Chrome / reimpresión: sale de lo guardado.
        $this->actingAs($this->cajero)->get("/venta/{$sale->id}/ticket")
            ->assertOk()->assertSeeInOrder(['Recibido', '$200.00', 'Cambio', '$128.00']);

        // Bluetooth sin "recibido" en la URL (estación de impresión, reintento).
        $texts = collect($this->actingAs($this->cajero)->getJson("/venta/{$sale->id}/impresion-bluetooth")->json('ticket'))->pluck('text')->implode("\n");
        $this->assertStringContainsString('Recibido', $texts);
        $this->assertStringContainsString('$128.00', $texts);
    }

    public function test_efectivo_exacto_sin_dato_muestra_cambio_cero_y_tarjeta_no_muestra_nada(): void
    {
        $this->cobrar(['domo'])->assertOk(); // no mandó amount_received
        $cash = Sale::latest('id')->first();
        $this->assertSame((string) $cash->total, (string) $cash->amount_received);
        $this->actingAs($this->cajero)->get("/venta/{$cash->id}/ticket")->assertSeeInOrder(['Recibido', 'Cambio', '$0.00']);

        $this->cobrar(['domo'], ['payment_method' => 'tarjeta', 'amount_received' => 500])->assertOk();
        $card = Sale::latest('id')->first();
        $this->assertNull($card->amount_received);
        $this->actingAs($this->cajero)->get("/venta/{$card->id}/ticket")->assertDontSee('Recibido');
    }

    public function test_la_pausa_entre_tickets_se_configura(): void
    {
        BusinessSettings::current()->update(['print_delay_seconds' => 7]);
        $this->cobrar(['domo'])->assertOk();

        $this->actingAs($this->cajero)->getJson('/venta/'.Sale::latest('id')->value('id').'/impresion-bluetooth')
            ->assertJson(['delay_seconds' => 7]);
    }

    // --- Transferencia ---

    public function test_se_cobra_por_transferencia_y_no_entra_al_efectivo_de_la_caja(): void
    {
        $this->cobrar(['domo'], ['payment_method' => 'transferencia'])->assertOk();

        $this->assertSame('transferencia', Sale::latest('id')->value('payment_method'));
        $this->assertEquals(500, $this->session->fresh()->calculateExpectedCash());
    }

    // --- Deshacer / corregir ---

    public function test_deshacer_regresa_la_orden_y_el_inventario_y_corregir_la_liga(): void
    {
        $papa = Ingredient::create(['name' => 'Papa', 'unit' => 'g', 'stock_qty' => 1000, 'min_stock' => 0, 'cost_per_unit' => 0.05, 'is_active' => true]);
        RecipeItem::create(['product_id' => $this->papas->id, 'ingredient_id' => $papa->id, 'qty' => 200]);
        $this->cobrar(['mayonesa', 'domo', 'queso'])->assertOk();
        $original = Sale::latest('id')->first();
        $this->assertEquals(800, $papa->fresh()->stock_qty);

        $order = $this->actingAs($this->cajero)->postJson("/venta/{$original->id}/deshacer", ['reason' => 'Error de captura'])
            ->assertOk()->json('order');

        $this->assertSame($original->id, $order['sale_id']);
        $this->assertSame(['Domo', 'Queso', 'Mayonesa'], $order['items'][0]['modifierNames']);
        $this->assertSame('sin sal', $order['items'][0]['notes']);
        $original->refresh();
        $this->assertSame('deshecha', $original->revert_type);
        $this->assertEquals(0, $original->total);
        $this->assertEquals(1000, $papa->fresh()->stock_qty);
        $this->assertEquals(500, $this->session->fresh()->calculateExpectedCash());

        // Se corrige y se vuelve a cobrar
        $this->cobrar(['domo', 'queso'], ['corrects_sale_id' => $original->id])->assertOk();
        $correccion = Sale::latest('id')->first();
        $this->assertSame('corregida', $original->fresh()->revert_type);
        $this->assertSame($correccion->id, $original->fresh()->replaced_by_sale_id);
        $this->actingAs($this->cajero)->get("/venta/{$correccion->id}/comanda")->assertSee('CORRECCIÓN de #'.$original->folio);
        $this->actingAs($this->cajero)->get('/venta/historial')->assertSee('Corregida → #'.$correccion->folio)->assertSee('Corrige a #'.$original->folio);
    }

    public function test_reglas_para_deshacer(): void
    {
        $otroCajero = $this->user('cajero', '2222');
        $manager = $this->user('manager', '9999');

        $this->cobrar(['domo'])->assertOk();
        $mia = Sale::latest('id')->first();

        $this->actingAs($otroCajero)->postJson("/venta/{$mia->id}/deshacer", ['reason' => 'Error de captura'])
            ->assertStatus(422)->assertJson(['message' => 'Solo puedes deshacer tus propias ventas. Pídeselo al manager o gerente.']);

        $mia->forceFill(['created_at' => now()->subMinutes(6)])->save();
        $this->actingAs($this->cajero)->postJson("/venta/{$mia->id}/deshacer", ['reason' => 'Error de captura'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Ya pasaron más de 5 minutos. Pídele al manager o gerente que la deshaga, o cancélala con código.']);

        // El manager sí, aunque ya pasaron los minutos y no es suya
        $this->actingAs($manager)->postJson("/venta/{$mia->id}/deshacer", ['reason' => 'Queja'])->assertOk();
        $this->actingAs($manager)->postJson("/venta/{$mia->id}/deshacer", ['reason' => 'Otra vez'])->assertStatus(422);
    }

    public function test_deshacer_le_quita_al_cliente_la_visita(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 2]);
        $this->cobrar(['domo'], ['customer_id' => $customer->id])->assertOk();
        $this->assertSame(3, $customer->fresh()->current_visits);

        $this->actingAs($this->cajero)->postJson('/venta/'.Sale::latest('id')->value('id').'/deshacer', ['reason' => 'Error de captura'])->assertOk();

        $this->assertSame(2, $customer->fresh()->current_visits);
    }

    // --- Caja ---

    public function test_al_abrir_se_propone_el_fondo_que_se_dejo_al_cerrar(): void
    {
        $this->session->update(['closed_at' => now(), 'fund_counted_amount' => 650]);

        $this->actingAs($this->cajero)->get('/caja/abrir')->assertOk()->assertSee('¿Empiezas con', false)->assertSee('$650.00');
    }

    public function test_cierre_con_dos_conteos_fondo_y_ventas(): void
    {
        $this->actingAs($this->cajero)->get('/caja/cerrar/inventario'); // sin insumos: pasa directo
        $this->cobrar(['domo'])->assertOk(); // $60 en efectivo

        // Sin cuadrar y sin motivo: no deja
        $this->actingAs($this->cajero)->postJson('/caja/cerrar', [
            'fund' => ['matches' => true],
            'sales' => ['matches' => false, 'denominations' => [['value' => 50, 'qty' => 1]]],
        ])->assertStatus(422)->assertJson(['message' => 'Escribe por qué no cuadra.']);

        $this->actingAs($this->cajero)->postJson('/caja/cerrar', [
            'fund' => ['matches' => true],
            'sales' => ['matches' => false, 'denominations' => [['value' => 50, 'qty' => 1]]],
            'difference_reason' => 'Se dio cambio de más',
        ])->assertOk();

        $closed = $this->session->fresh();
        $this->assertEquals(500, $closed->fund_counted_amount);
        $this->assertEquals(0, $closed->fund_difference);
        $this->assertEquals(60, $closed->sales_expected_amount);
        $this->assertEquals(50, $closed->sales_counted_amount);
        $this->assertEquals(-10, $closed->sales_difference);
        $this->assertEquals(550, $closed->counted_amount);
        $this->assertSame('ventas', $closed->hasMany(CashSessionDenomination::class)->value('kind'));

        $this->actingAs($this->cajero)->get("/caja/{$closed->id}/reporte-cierre")->assertOk()
            ->assertSeeInOrder(['1. Fondo de caja', '2. Ventas del turno', 'Transferencia', '3. Dinero de ventas', 'Falta', '4. Total en caja', 'Se dio cambio de más']);

        $bt = array_column($this->actingAs($this->cajero)->getJson("/caja/{$closed->id}/reporte-cierre-bluetooth")->json('ticket'), 'text');
        $this->assertContains('1. FONDO DE CAJA', $bt);
        $this->assertContains('4. TOTAL EN CAJA', $bt);
    }

    public function test_se_puede_corregir_el_fondo_durante_el_turno(): void
    {
        $this->actingAs($this->cajero)->postJson('/caja/fondo', ['opening_amount' => 800])->assertOk();
        $this->assertEquals(800, $this->session->fresh()->opening_amount);
    }

    // --- Clientes ---

    public function test_cumpleanos_en_registro_y_alta_y_edicion_desde_la_venta(): void
    {
        $this->post('/registro', ['name' => 'Luis', 'phone' => '5533334444', 'birthdate' => '1995-03-15'])->assertRedirect();
        $this->assertSame('1995-03-15', Customer::where('phone', '5533334444')->first()->birthdate->toDateString());

        $id = $this->actingAs($this->cajero)->postJson('/venta/clientes', ['name' => 'Mar', 'phone' => '55 5566 7788', 'birthdate' => '2000-01-02'])
            ->assertOk()->json('id');
        $this->assertSame($id, session('selected_customer_id'));

        $this->actingAs($this->cajero)->postJson("/venta/clientes/{$id}", ['name' => 'Mar Pérez', 'phone' => '5533334444'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->actingAs($this->cajero)->postJson("/venta/clientes/{$id}", ['name' => 'Mar Pérez', 'phone' => '5555667788', 'birthdate' => '2000-01-03'])
            ->assertOk()->assertJson(['name' => 'Mar Pérez']);
        $this->assertSame('2000-01-03', Customer::find($id)->birthdate->toDateString());
    }
}
