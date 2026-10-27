<?php

namespace Tests\Feature;

use App\Filament\Resources\DayPasses\DayPassResource;
use App\Filament\Resources\DayPasses\Pages\ManageDayPasses;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\DayPass;
use App\Models\LoyaltyLevel;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleRevertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Pase del día: código de un solo uso para gente que no está en las tarjetas. */
class DayPassTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Product $papas;

    private Product $refresco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::create(['name' => 'Gerente', 'email' => 'm@test.local', 'password' => 'x', 'pin' => '1234',
            'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true]);
        CashSession::create(['user_id' => $this->manager->id, 'opening_amount' => 500, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $this->papas = Product::create(['category_id' => $category->id, 'name' => 'Papis', 'base_price' => 100, 'is_active' => true]);
        $this->refresco = Product::create(['category_id' => $category->id, 'name' => 'Refresco', 'base_price' => 25, 'is_active' => true]);
    }

    private function pase(array $attrs = []): DayPass
    {
        return DayPass::create([
            'benefit_type' => 'percent', 'value' => 10, 'expires_on' => today(), 'created_by' => $this->manager->id, ...$attrs,
        ]);
    }

    private function cobrar(?string $code, array $extra = [])
    {
        return $this->actingAs($this->manager)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $this->papas->id, 'qty' => 1]], 'payment_method' => 'efectivo', 'day_pass_code' => $code, ...$extra,
        ]);
    }

    public function test_el_codigo_tiene_8_caracteres_sin_letras_que_se_confundan(): void
    {
        foreach (range(1, 50) as $i) {
            $code = $this->pase()->code;
            $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $code);
        }
        $this->assertSame(50, DayPass::distinct()->count('code'));
    }

    public function test_porcentaje_y_monto_se_descuentan_y_el_pase_queda_usado(): void
    {
        $pase = $this->pase(['value' => 15]);
        $this->cobrar(strtolower(substr($pase->code, 0, 4)).'-'.substr($pase->code, 4))->assertOk(); // como lo escriba el cajero

        $sale = Sale::latest('id')->first();
        $this->assertEquals(85, $sale->total);
        $this->assertSame($pase->id, $sale->day_pass_id);
        $this->assertSame($sale->id, $pase->fresh()->used_sale_id);
        $this->assertSame('pase', $sale->discount_breakdown[0]['kind']);

        $monto = $this->pase(['benefit_type' => 'amount', 'value' => 150]); // nunca más que la cuenta
        $this->cobrar($monto->code)->assertOk();
        $this->assertEquals(0, Sale::latest('id')->first()->total);
    }

    public function test_producto_gratis_se_agrega_solo_si_no_esta_en_el_carrito(): void
    {
        $pase = $this->pase(['benefit_type' => 'product', 'product_id' => $this->refresco->id, 'value' => null]);

        $this->actingAs($this->manager)->postJson('/venta/promociones/preview', [
            'items' => [['product_id' => $this->papas->id, 'qty' => 1]], 'day_pass_code' => $pase->code,
        ])->assertOk()->assertJsonPath('day_pass.auto_added.name', 'Refresco')->assertJsonPath('total', 100);

        $this->cobrar($pase->code)->assertOk();
        $sale = Sale::latest('id')->with('items')->first();
        $this->assertCount(2, $sale->items);
        $this->assertEquals(125, $sale->subtotal);
        $this->assertEquals(100, $sale->total);
    }

    public function test_se_usa_una_sola_vez_y_no_sirve_vencido_ni_inventado(): void
    {
        $pase = $this->pase();
        $this->cobrar($pase->code)->assertOk();
        $folio = Sale::latest('id')->value('folio');

        $this->cobrar($pase->code)->assertStatus(422)->assertJsonPath('errors.day_pass_code.0', "Ese pase ya se usó (venta #{$folio}).");

        $vencido = $this->pase(['expires_on' => today()->subDay()]);
        $this->cobrar($vencido->code)->assertStatus(422)->assertJsonValidationErrors('day_pass_code');
        $this->cobrar('ZZZZZZZZ')->assertStatus(422)->assertJsonPath('errors.day_pass_code.0', 'Ese código de pase no existe. Revisa que esté bien escrito.');
        $this->assertSame(1, Sale::count());
    }

    public function test_no_se_junta_con_el_premio_de_la_tarjeta_que_queda_pendiente(): void
    {
        LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 20, 'is_active' => true]);
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '5580000001', 'current_level' => 1, 'current_visits' => 3]);
        $pase = $this->pase();

        $this->cobrar($pase->code, ['customer_id' => $chuy->id, 'redeem_reward' => true])->assertOk();

        $this->assertEquals(90, Sale::latest('id')->first()->total); // solo el 10% del pase
        $this->assertSame(4, $chuy->fresh()->current_visits);         // el sello sí cuenta
        $this->assertSame('pending', CustomerLoyaltyRedemption::where('customer_id', $chuy->id)->value('status'));
    }

    public function test_el_cajero_revisa_el_codigo_aunque_el_carrito_este_vacio(): void
    {
        $pase = $this->pase(['benefit_type' => 'product', 'product_id' => $this->refresco->id, 'value' => null, 'note' => 'Vecino']);

        $this->actingAs($this->manager)->postJson('/venta/pase', ['day_pass_code' => strtolower($pase->code)])
            ->assertOk()
            ->assertJson(['code' => $pase->code, 'label' => 'Refresco gratis', 'note' => 'Vecino', 'benefit_type' => 'product', 'product_id' => $this->refresco->id]);

        $this->actingAs($this->manager)->postJson('/venta/pase', ['day_pass_code' => 'ZZZZZZZZ'])->assertStatus(422);
        $this->assertNull($pase->fresh()->used_at); // revisarlo no lo gasta
    }

    public function test_deshacer_la_venta_libera_el_pase(): void
    {
        $pase = $this->pase();
        $this->cobrar($pase->code)->assertOk();

        $order = app(SaleRevertService::class)->revert(Sale::latest('id')->first(), $this->manager, 'Error de captura');

        $this->assertNull($pase->fresh()->used_at);
        $this->assertSame($pase->code, $order['day_pass_code']);
        $this->cobrar($pase->code)->assertOk();
    }

    public function test_el_manager_lo_genera_desde_el_panel_y_el_cajero_no_entra(): void
    {
        Livewire::actingAs($this->manager)
            ->test(ManageDayPasses::class)
            ->callAction('create', data: ['benefit_type' => 'product', 'product_id' => $this->refresco->id,
                'expires_on' => today()->addDays(3)->toDateString(), 'note' => 'Proveedor de papas'])
            ->assertHasNoActionErrors();

        $pase = DayPass::firstOrFail();
        $this->assertSame('Refresco gratis', $pase->benefitLabel());
        $this->assertSame($this->manager->id, $pase->created_by);
        $this->assertNull($pase->value);

        $cajero = User::create(['name' => 'Cajero', 'email' => 'c@test.local', 'password' => 'x', 'pin' => '4321',
            'role_id' => Role::firstOrCreate(['name' => 'cajero'])->id, 'is_active' => true]);
        $this->actingAs($cajero);
        $this->assertFalse(DayPassResource::canAccess());
    }
}
