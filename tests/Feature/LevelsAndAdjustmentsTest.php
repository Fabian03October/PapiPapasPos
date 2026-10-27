<?php

namespace Tests\Feature;

use App\Filament\Pages\LoyaltyAdjustmentsLog;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyAdjustment;
use App\Models\LoyaltyLevel;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\SaleRevertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Niveles que se repiten (en ciclo) y ajuste a mano de sellos con bitácora. */
class LevelsAndAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Product $papas;

    private LoyaltyLevel $nivel1;

    private LoyaltyLevel $nivel2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::create(['name' => 'Gerente', 'email' => 'm@test.local', 'password' => 'x', 'pin' => '1234',
            'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true]);
        CashSession::create(['user_id' => $this->manager->id, 'opening_amount' => 500, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $this->papas = Product::create(['category_id' => $category->id, 'name' => 'Papis', 'base_price' => 60, 'is_active' => true]);
        $this->nivel1 = LoyaltyLevel::create(['level_number' => 1, 'repeat_times' => 2, 'discount_percent' => 10, 'is_active' => true]);
        $this->nivel2 = LoyaltyLevel::create(['level_number' => 2, 'repeat_times' => 1, 'discount_percent' => 20, 'is_active' => true]);
    }

    private function llenarTarjeta(Customer $customer): void
    {
        Customer::whereKey($customer->id)->update(['current_visits' => 7]);
        $this->travel(1)->days(); // un sello al día
        $this->actingAs($this->manager)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $this->papas->id, 'qty' => 1]], 'payment_method' => 'efectivo', 'customer_id' => $customer->id,
        ])->assertOk();
    }

    public function test_el_nivel_se_repite_las_veces_que_dice_y_despues_del_ultimo_regresa_al_primero(): void
    {
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '5580000001', 'current_level' => 1]);

        $this->llenarTarjeta($chuy); // 1ª tarjeta de nivel 1 (se repite 2)
        $this->assertSame([1, 0, 1], [$chuy->fresh()->current_level, $chuy->fresh()->current_visits, $chuy->fresh()->cards_completed]);

        $this->llenarTarjeta($chuy); // 2ª → sube a nivel 2
        $this->assertSame([2, 0, 0], [$chuy->fresh()->current_level, $chuy->fresh()->current_visits, $chuy->fresh()->cards_completed]);

        $this->llenarTarjeta($chuy); // nivel 2 es el último (1 vez) → regresa al 1
        $this->assertSame([1, 0, 0], [$chuy->fresh()->current_level, $chuy->fresh()->current_visits, $chuy->fresh()->cards_completed]);
    }

    public function test_deshacer_la_venta_que_lleno_la_tarjeta_regresa_todo(): void
    {
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '5580000002', 'current_level' => 1, 'cards_completed' => 1]);

        $this->llenarTarjeta($chuy); // 2ª tarjeta → sube a nivel 2
        $this->assertSame(2, $chuy->fresh()->current_level);

        app(SaleRevertService::class)->revert(Sale::latest('id')->first(), $this->manager, 'Error de captura');

        $this->assertSame([1, 7, 1], [$chuy->fresh()->current_level, $chuy->fresh()->current_visits, $chuy->fresh()->cards_completed]);
    }

    public function test_la_tarjeta_dice_cuantas_lleva_cuando_el_nivel_se_repite(): void
    {
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '5580000003', 'current_level' => 1, 'cards_completed' => 1]);
        $this->get("/mi-tarjeta/{$chuy->qr_code}")->assertSee('Tarjeta 2 de 2 para subir de nivel');

        $chuy->update(['current_level' => 2, 'cards_completed' => 0]);
        $this->get("/mi-tarjeta/{$chuy->qr_code}")->assertDontSee('Tarjeta 1 de'); // nivel 2 no se repite
    }

    public function test_el_manager_pasa_la_tarjeta_fisica_con_premio_y_queda_en_la_bitacora(): void
    {
        // Chuy: ganó el descuento en su tarjeta física (no lo ha usado) y lleva 4 sellos.
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '5580000004']);

        app(LoyaltyService::class)->adjust($chuy, $this->manager, level: 1, visits: 4, cards: 1, reason: 'Pasa de tarjeta física', reward: 'discount');

        $chuy->refresh();
        $this->assertSame([1, 4, 1], [$chuy->current_level, $chuy->current_visits, $chuy->cards_completed]);
        $this->assertSame('pending', CustomerLoyaltyRedemption::where('customer_id', $chuy->id)->value('status'));

        $log = LoyaltyAdjustment::firstOrFail();
        $this->assertEquals(['level' => 1, 'visits' => 0, 'cards' => 0], $log->before);
        $this->assertEquals(['level' => 1, 'visits' => 4, 'cards' => 1], $log->after);
        $this->assertSame('Pasa de tarjeta física', $log->reason);
        $this->assertSame($this->manager->id, $log->user_id);
    }

    public function test_el_ajuste_no_acepta_valores_imposibles(): void
    {
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '5580000005']);
        $service = app(LoyaltyService::class);

        foreach ([
            fn () => $service->adjust($chuy, $this->manager, 1, 8, 0, 'x'),   // 8 sellos: ya sería tarjeta llena
            fn () => $service->adjust($chuy, $this->manager, 1, 3, 2, 'x'),   // nivel 1 se llena 2 veces: máximo 1
            fn () => $service->adjust($chuy, $this->manager, 9, 3, 0, 'x'),   // no existe
            fn () => $service->adjust($chuy, $this->manager, 1, 3, 0, '  '),  // sin motivo
        ] as $intento) {
            try {
                $intento();
                $this->fail('Debió rechazarse');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, LoyaltyAdjustment::count());
    }

    public function test_desde_el_panel_solo_el_manager_ajusta_y_ve_la_bitacora(): void
    {
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '5580000006']);

        Livewire::actingAs($this->manager)
            ->test(ListCustomers::class)
            ->callTableAction('adjust_loyalty', $chuy, data: ['level' => 1, 'visits' => 5, 'cards' => 0, 'reward' => 'none', 'reason' => 'Tarjeta física'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(5, $chuy->fresh()->current_visits);

        $cajero = User::create(['name' => 'Cajero', 'email' => 'c@test.local', 'password' => 'x', 'pin' => '4321',
            'role_id' => Role::firstOrCreate(['name' => 'cajero'])->id, 'is_active' => true]);
        $this->actingAs($cajero);
        $this->assertFalse(LoyaltyAdjustmentsLog::canAccess());
        $this->actingAs($this->manager);
        $this->assertTrue(LoyaltyAdjustmentsLog::canAccess());
    }
}
