<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyLevel;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleRevertService;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Sellos y premios: que no se pierdan ni se den dos veces. */
class LoyaltyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private Product $papas;

    private LoyaltyLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = User::create([
            'name' => 'Cajero', 'email' => 'cajero@test.local', 'password' => 'password', 'pin' => '1111',
            'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true,
        ]);
        CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 500, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $this->papas = Product::create(['category_id' => $category->id, 'name' => 'Papas', 'base_price' => 100, 'is_active' => true]);
        $this->level = LoyaltyLevel::create(['level_number' => 1, 'discount_percent' => 10, 'is_active' => true]);
    }

    private function cobrar(Customer $customer, array $extra = [])
    {
        return $this->actingAs($this->cajero)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $this->papas->id, 'qty' => 1]],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            ...$extra,
        ]);
    }

    /** Corre $fn una sola vez, justo cuando la venta empieza a guardarse. */
    private function otraTabletAlMismoTiempo(callable $fn): void
    {
        $done = false;
        Event::listen(TransactionBeginning::class, function () use (&$done, $fn) {
            if (! $done) {
                $done = true;
                $fn();
            }
        });
    }

    public function test_venta_simultanea_al_mismo_cliente_no_pierde_sellos(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000001', 'current_level' => 1, 'current_visits' => 2]);

        // Otra tablet le sumó un sello entre el cálculo y el guardado.
        $this->otraTabletAlMismoTiempo(fn () => DB::table('customers')->where('id', $customer->id)->update(['current_visits' => 3]));

        $this->cobrar($customer)
            ->assertStatus(409)
            ->assertJsonPath('message', 'El cliente acaba de registrar otra visita. Vuelve a tocar Cobrar.');

        // Nada se guardó. (En la prueba, el cambio de "la otra tablet" ocurre
        // dentro de la misma transacción y se deshace con ella; en la vida
        // real esa otra venta ya está guardada y se respeta.)
        $this->assertSame(0, Sale::count());
        $this->assertSame(2, $customer->fresh()->current_visits);

        // Al volver a cobrar, con el dato al día, sí pasa.
        $this->cobrar($customer)->assertOk();
        $this->assertSame(3, $customer->fresh()->current_visits);
    }

    public function test_un_premio_pendiente_no_se_cobra_dos_veces(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000002', 'current_level' => 1, 'current_visits' => 5]);
        $pending = CustomerLoyaltyRedemption::create([
            'customer_id' => $customer->id, 'loyalty_level_id' => $this->level->id, 'type' => 'discount',
            'status' => 'pending', 'earned_at' => now()->subDay(), 'expires_at' => now()->addDays(6),
        ]);

        // La otra tablet lo cobró en ese mismo momento.
        $this->otraTabletAlMismoTiempo(fn () => DB::table('customer_loyalty_redemptions')->where('id', $pending->id)->update(['status' => 'redeemed']));

        $this->cobrar($customer, ['redeem_reward' => true])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ese premio ya se cobró en otra venta. Vuelve a tocar Cobrar.');

        $this->assertSame(0, Sale::count());
    }

    public function test_cliente_dado_de_baja_no_junta_sellos(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000003', 'is_active' => false]);

        $this->cobrar($customer)->assertStatus(422)->assertJsonValidationErrors('customer_id');
        $this->assertSame(0, (int) $customer->fresh()->current_visits);
    }

    public function test_deshacer_no_regresa_un_premio_que_ya_se_cobro_en_otra_venta(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000004', 'current_level' => 1, 'current_visits' => 3]);

        // 4ª visita: gana el descuento y lo deja pendiente.
        $this->cobrar($customer)->assertOk();
        $first = Sale::latest('id')->first();
        $earned = CustomerLoyaltyRedemption::where('sale_id', $first->id)->firstOrFail();
        $this->assertSame('pending', $earned->status);

        // 5ª visita, al día siguiente (un sello al día): lo cobra.
        $this->travel(1)->days();
        $this->cobrar($customer, ['redeem_reward' => true])->assertOk();
        $this->assertSame('redeemed', $earned->fresh()->status);
        $this->assertSame(5, $customer->fresh()->current_visits);

        // Se deshace la 4ª: el premio ya se disfrutó en la 5ª → no se quita
        // ni la visita ni el premio (si no, lo podría volver a ganar).
        app(SaleRevertService::class)->revert($first, $this->cajero, 'Error de captura');

        $this->assertSame(5, $customer->fresh()->current_visits);
        $this->assertNotNull($earned->fresh());
    }

    public function test_deshacer_la_venta_que_cobro_un_premio_lo_deja_otra_vez_por_cobrar(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000005', 'current_level' => 1, 'current_visits' => 5]);
        $pending = CustomerLoyaltyRedemption::create([
            'customer_id' => $customer->id, 'loyalty_level_id' => $this->level->id, 'type' => 'discount',
            'status' => 'pending', 'earned_at' => now()->subDay(), 'expires_at' => now()->addDays(6),
        ]);

        $this->cobrar($customer, ['redeem_reward' => true])->assertOk();
        $sale = Sale::latest('id')->first();
        $this->assertSame('redeemed', $pending->fresh()->status);

        app(SaleRevertService::class)->revert($sale, $this->cajero, 'Error de captura');

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame(5, $customer->fresh()->current_visits);
    }

    public function test_dos_clientes_no_pueden_tener_el_mismo_telefono(): void
    {
        Customer::create(['name' => 'Ana', 'phone' => '5510000006']);

        $this->expectException(UniqueConstraintViolationException::class);
        Customer::create(['name' => 'Otra', 'phone' => '5510000006']);
    }

    public function test_un_sello_al_dia_la_segunda_compra_no_suma(): void
    {
        $customer = Customer::create(['name' => 'Chuy', 'phone' => '5510000010', 'current_level' => 1, 'current_visits' => 1]);

        $this->cobrar($customer)->assertOk();
        $this->assertSame(2, $customer->fresh()->current_visits);

        // Vuelve el mismo día: el cajero ve el aviso y no suma.
        $this->actingAs($this->cajero)->postJson('/venta/promociones/preview', [
            'items' => [['product_id' => $this->papas->id, 'qty' => 1]], 'customer_id' => $customer->id,
        ])->assertJsonPath('visit_note', 'Chuy ya recibió su sello de hoy: esta compra no suma visita.');
        $this->cobrar($customer)->assertOk();
        $this->assertSame(2, $customer->fresh()->current_visits);
        $this->assertFalse(Sale::latest('id')->first()->counted_visit);

        // Al día siguiente sí.
        $this->travel(1)->days();
        $this->cobrar($customer)->assertOk();
        $this->assertSame(3, $customer->fresh()->current_visits);
    }

    public function test_en_la_segunda_compra_del_dia_si_puede_cobrar_un_premio_pendiente(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000011', 'current_level' => 1, 'current_visits' => 5]);
        $this->cobrar($customer)->assertOk(); // su sello de hoy
        $pending = CustomerLoyaltyRedemption::create(['customer_id' => $customer->id, 'loyalty_level_id' => $this->level->id,
            'type' => 'discount', 'status' => 'pending', 'earned_at' => now()->subDay(), 'expires_at' => now()->addDays(6)]);

        $this->cobrar($customer, ['redeem_reward' => true])->assertOk()->assertJsonPath('loyalty.source', 'pending');

        $this->assertSame('redeemed', $pending->fresh()->status);
        $this->assertSame(6, $customer->fresh()->current_visits); // no sumó otra
    }

    public function test_si_se_deshace_la_primera_del_dia_la_siguiente_si_cuenta(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000012', 'current_level' => 1, 'current_visits' => 1]);
        $this->cobrar($customer)->assertOk();
        app(SaleRevertService::class)->revert(Sale::latest('id')->first(), $this->cajero, 'Error de captura');
        $this->assertSame(1, $customer->fresh()->current_visits);

        $this->cobrar($customer)->assertOk();
        $this->assertSame(2, $customer->fresh()->current_visits);
    }

    public function test_deshacer_la_segunda_compra_del_dia_no_le_quita_su_sello(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000013', 'current_level' => 1, 'current_visits' => 1]);
        $this->cobrar($customer)->assertOk(); // 2
        $this->cobrar($customer)->assertOk(); // no suma

        app(SaleRevertService::class)->revert(Sale::latest('id')->first(), $this->cajero, 'Error de captura');

        $this->assertSame(2, $customer->fresh()->current_visits);
    }

    public function test_si_otra_tablet_le_dio_su_sello_de_hoy_en_ese_momento_no_se_duplica(): void
    {
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5510000014', 'current_level' => 1, 'current_visits' => 1]);
        $session = CashSession::open();

        $this->otraTabletAlMismoTiempo(fn () => DB::table('sales')->insert([
            'folio' => 'X1', 'user_id' => $this->cajero->id, 'cash_session_id' => $session->id, 'customer_id' => $customer->id,
            'counted_visit' => true, 'status' => 'pagada', 'subtotal' => 100, 'discount' => 0, 'total' => 100,
            'payment_method' => 'efectivo', 'created_at' => now(), 'updated_at' => now(),
        ]));

        $this->cobrar($customer)->assertStatus(409);
    }
}
