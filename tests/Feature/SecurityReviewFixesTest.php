<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleIncident;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\SaleIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lo que salió en la revisión completa del sistema (2026-10-15).
 */
class SecurityReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $pin, string $email = null): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . $pin, 'email' => $email ?? $role . $pin . '@test.local', 'password' => 'password',
            'pin' => $pin, 'role_id' => Role::firstOrCreate(['name' => $role])->id, 'is_active' => true,
        ]);
    }

    // --- PIN ---

    public function test_el_enlace_para_recuperar_el_pin_vence_a_los_60_minutos(): void
    {
        $user = $this->user('cajero', '1111', 'ana@test.local');

        DB::table('pin_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make('viejo'), 'created_at' => now()->subHours(5)]);
        $this->postJson('/login/restablecer-pin', ['email' => $user->email, 'token' => 'viejo', 'new_pin' => '4321'])
            ->assertStatus(422)->assertJson(['message' => 'Este enlace ya expiró, pide uno nuevo.']);

        DB::table('pin_reset_tokens')->where('email', $user->email)->update(['token' => Hash::make('nuevo'), 'created_at' => now()->subMinutes(10)]);
        $this->postJson('/login/restablecer-pin', ['email' => $user->email, 'token' => 'nuevo', 'new_pin' => '4321'])->assertOk();
        $this->assertTrue(Hash::check('4321', $user->fresh()->pin));
    }

    public function test_con_un_pin_repetido_no_se_entra_como_otra_persona(): void
    {
        $this->user('manager', '1234');
        $this->user('cajero', '1234'); // quedó repetido de antes

        $this->postJson('/login', ['pin' => '1234'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Ese PIN lo tienen varias personas. Pidan al manager que les asigne PINs distintos.']);
        $this->assertGuest();
    }

    public function test_no_se_puede_elegir_un_pin_que_ya_usa_otra_persona(): void
    {
        $manager = $this->user('manager', '9999');
        $cajero = $this->user('cajero', '1111');

        // Al cambiarlo en el primer login
        $this->actingAs($cajero)->postJson('/login/cambiar-pin', ['new_pin' => '9999'])
            ->assertStatus(422)->assertJson(['message' => 'Ese PIN ya lo usa otra persona. Elige otro.']);
        $this->actingAs($cajero)->postJson('/login/cambiar-pin', ['new_pin' => '2222'])->assertOk();

        // Al darlo de alta en el admin
        Livewire::actingAs($manager)
            ->test(CreateUser::class)
            ->fillForm(['name' => 'Nuevo', 'email' => 'nuevo@test.local', 'password' => 'x', 'pin' => '2222',
                'role_id' => Role::where('name', 'cajero')->value('id'), 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['pin']);
    }

    public function test_el_login_tiene_limite_de_intentos(): void
    {
        $this->user('cajero', '1111');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/login', ['pin' => '0000'])->assertStatus(422);
        }

        $this->postJson('/login', ['pin' => '1111'])
            ->assertStatus(429)
            ->assertJson(['message' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.']);
    }

    // --- Cancelaciones ---

    private function saleItemIn(CashSession $session, User $by): SaleItem
    {
        $category = Category::firstOrCreate(['name' => 'Papas'], ['sort_order' => 1, 'is_active' => true]);
        $product = Product::firstOrCreate(['name' => 'Salchipapas'], ['category_id' => $category->id, 'base_price' => 89, 'is_active' => true]);
        $sale = Sale::create([
            'folio' => 'F' . (Sale::count() + 1), 'user_id' => $by->id, 'cash_session_id' => $session->id, 'status' => 'pagada',
            'subtotal' => 89, 'discount' => 0, 'total' => 89, 'payment_method' => 'efectivo',
        ]);

        return SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'qty' => 1, 'unit_price' => 89, 'line_total' => 89]);
    }

    public function test_no_se_cancela_nada_de_un_turno_ya_cerrado(): void
    {
        $manager = $this->user('manager', '9999');
        $cerrado = CashSession::create(['user_id' => $manager->id, 'opening_amount' => 0, 'opened_at' => now()->subDay(), 'closed_at' => now()->subHours(12)]);
        $itemViejo = $this->saleItemIn($cerrado, $manager);
        CashSession::create(['user_id' => $manager->id, 'opening_amount' => 0, 'opened_at' => now()]);

        $this->actingAs($manager)->postJson('/venta/incidencias', [
            'sale_item_ids' => [$itemViejo->id], 'type' => 'cancelacion', 'reason' => 'Se canceló ayer', 'mode' => 'directo',
        ])->assertStatus(422)->assertJson(['message' => 'Solo se pueden cancelar o reponer productos del turno abierto.']);

        $this->assertNull($itemViejo->fresh()->cancelled_at);
        $this->assertEquals(89, $itemViejo->sale->fresh()->total);
    }

    public function test_aprobar_dos_veces_una_reposicion_no_descuenta_inventario_doble(): void
    {
        $manager = $this->user('manager', '9999');
        $cajero = $this->user('cajero', '1111');
        $session = CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $item = $this->saleItemIn($session, $cajero);
        $papa = Ingredient::create(['name' => 'Papa', 'unit' => 'g', 'stock_qty' => 1000, 'min_stock' => 0, 'cost_per_unit' => 0.05, 'is_active' => true]);
        RecipeItem::create(['product_id' => $item->product_id, 'ingredient_id' => $papa->id, 'qty' => 200]);

        $service = new SaleIncidentService;
        $incident = $service->createPending([$item->id], 'reposicion', 'Se cayó al piso', $cajero);

        // Doble clic / dos personas aprobando a la vez (el mismo registro cargado dos veces)
        $copia = SaleIncident::find($incident->id);
        $service->approve($incident, $manager);
        $service->approve($copia, $manager);

        $this->assertEquals(800, $papa->fresh()->stock_qty);
    }

    // --- Admin ---

    public function test_no_se_aceptan_precios_negativos_ni_promociones_de_mas_del_100(): void
    {
        $manager = $this->user('manager', '9999');
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);

        Livewire::actingAs($manager)->test(CreateProduct::class)
            ->fillForm(['name' => 'Papas', 'category_id' => $category->id, 'base_price' => -10, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['base_price']);

        Livewire::actingAs($manager)->test(CreatePromotion::class)
            ->fillForm(['name' => 'Super promo', 'type' => 'discount', 'scope' => 'product', 'discount_mode' => 'percent', 'percent_value' => 150, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['percent_value']);
    }

    // --- Venta ---

    public function test_el_servidor_rechaza_variantes_o_extras_ajenos_o_desactivados(): void
    {
        $cajero = $this->user('cajero', '1111');
        CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $papas = Product::create(['category_id' => $category->id, 'name' => 'Papas', 'base_price' => 60, 'is_active' => true]);
        $refresco = Product::create(['category_id' => $category->id, 'name' => 'Refresco', 'base_price' => 35, 'is_active' => true]);
        $varianteDelRefresco = ProductVariant::create(['product_id' => $refresco->id, 'name' => 'Coca', 'price_delta' => 0, 'is_active' => true]);
        $extras = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $papas->modifierGroups()->attach($extras->id);
        $queso = Modifier::create(['modifier_group_id' => $extras->id, 'name' => 'Queso', 'price_delta' => 15, 'is_active' => true]);
        $tocinoDesactivado = Modifier::create(['modifier_group_id' => $extras->id, 'name' => 'Tocino', 'price_delta' => 20, 'is_active' => false]);

        $cobrar = fn (array $item) => $this->actingAs($cajero)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $papas->id, 'qty' => 1, ...$item]], 'payment_method' => 'efectivo',
        ]);

        $cobrar(['variant_id' => $varianteDelRefresco->id])->assertStatus(422)->assertJsonValidationErrors('items');
        $cobrar(['modifier_ids' => [$tocinoDesactivado->id]])->assertStatus(422)
            ->assertJsonFragment(['Un extra de "Papas" ya no está disponible. Quítalo del carrito y vuelve a agregarlo.']);
        $this->assertSame(0, Sale::count());

        $cobrar(['modifier_ids' => [$queso->id]])->assertOk()->assertJson(['total' => 75.0]);
    }
}
