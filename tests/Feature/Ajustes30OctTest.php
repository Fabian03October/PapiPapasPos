<?php

namespace Tests\Feature;

use App\Filament\Resources\Ingredients\Pages\ListIngredients;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\RecipeItemsRelationManager;
use Filament\Actions\Testing\TestAction;
use App\Models\AuditLog;
use App\Models\BusinessSettings;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\InventoryCount;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Promotion;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Ajustes del 30 de octubre: candado del gerente, bitácora, conteo, WhatsApp, panel sin caja. */
class Ajustes30OctTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $pin): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.'@test.local', 'password' => 'password',
            'pin' => $pin, 'role_id' => Role::firstOrCreate(['name' => $role])->id, 'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);
    }

    public function test_el_gerente_ve_pero_no_crea_ni_edita(): void
    {
        $product = $this->product();
        $gerente = $this->user('gerente', '1111');

        $this->actingAs($gerente)->get('/admin/products')->assertOk();
        $this->actingAs($gerente)->get('/admin/products/create')->assertForbidden();
        $this->actingAs($gerente)->get("/admin/products/{$product->id}/edit")->assertForbidden();
        $this->actingAs($gerente)->get('/admin/customers')->assertOk()->assertDontSee('Cambiar enlace');
        $this->actingAs($gerente)->get('/admin/bitacora-de-cambios')->assertForbidden();

        // Insumos: el gerente sí da de alta y registra entradas, pero no edita.
        $papa = Ingredient::create(['name' => 'Papa', 'unit' => 'kg', 'stock_qty' => 10, 'cost_per_unit' => 20, 'is_active' => true]);
        $this->actingAs($gerente)->get('/admin/ingredients/create')->assertOk();
        $this->actingAs($gerente)->get("/admin/ingredients/{$papa->id}/edit")->assertForbidden();
        Livewire::actingAs($gerente)->test(ListIngredients::class)
            ->callTableAction('registrarEntrada', $papa, ['qty' => 5, 'unit_cost' => 20])
            ->assertHasNoTableActionErrors();
        $this->assertEquals(15, $papa->fresh()->stock_qty);
    }

    public function test_el_manager_si_crea_y_edita(): void
    {
        $product = $this->product();
        $manager = $this->user('manager', '2222');

        $this->actingAs($manager)->get('/admin/products/create')->assertOk();
        $this->actingAs($manager)->get("/admin/products/{$product->id}/edit")->assertOk();
    }

    public function test_lo_que_cambia_el_manager_en_el_panel_queda_en_la_bitacora(): void
    {
        $product = $this->product();
        $manager = $this->user('manager', '2222');

        Filament::setServingStatus(); // como en una petición real del panel
        Livewire::actingAs($manager)
            ->test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['base_price' => 95])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = AuditLog::sole();
        $this->assertSame('updated', $log->action);
        $this->assertSame($manager->id, $log->user_id);
        $this->assertSame('Producto: Salchipapas', $log->modelName().': '.$log->label);
        $this->assertEquals(89, $log->changes['base_price']['antes']);
        $this->assertEquals(95, $log->changes['base_price']['despues']);

        $this->actingAs($manager)->get('/admin/bitacora-de-cambios')->assertOk()->assertSee('Salchipapas');

        // Lo que pasa fuera del panel (ventas, POS) no se anota.
        Filament::setServingStatus(false);
        $product->update(['base_price' => 99]);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_el_manager_decide_si_se_cuenta_al_cerrar_y_puede_contar_cuando_quiera(): void
    {
        $manager = $this->user('manager', '2222');
        $cajero = $this->user('cajero', '3333');
        $papa = Ingredient::create(['name' => 'Papa', 'unit' => 'kg', 'stock_qty' => 10, 'cost_per_unit' => 20, 'is_active' => true]);
        CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);

        // Encendido (como hasta hoy): pide el conteo.
        $this->actingAs($cajero)->get('/caja/cerrar')->assertRedirect('/caja/cerrar/inventario');

        // Contar a cualquier hora: solo el manager; no cuenta como el del cierre.
        $this->actingAs($cajero)->get('/inventario/contar')->assertForbidden();
        $this->actingAs($manager)->get('/inventario/contar')->assertOk()->assertSee('Papa');
        $this->actingAs($manager)->postJson('/inventario/contar', ['counts' => [$papa->id => 7]])->assertOk();
        $this->assertEquals(7, $papa->fresh()->stock_qty);
        $this->assertNull(InventoryCount::sole()->cash_session_id);
        $this->actingAs($cajero)->get('/caja/cerrar')->assertRedirect('/caja/cerrar/inventario');

        // Cada semana: como se contó hoy, no lo pide; si pasaron 8 días, sí.
        BusinessSettings::current()->update(['inventory_count_every_days' => 7]);
        $this->actingAs($cajero)->get('/caja/cerrar')->assertOk();
        InventoryCount::query()->update(['created_at' => now()->subDays(8)]);
        $this->actingAs($cajero)->get('/caja/cerrar')->assertRedirect('/caja/cerrar/inventario');

        // Nunca: se cierra sin contar.
        BusinessSettings::current()->update(['inventory_count_every_days' => 0]);
        $this->actingAs($cajero)->get('/caja/cerrar')->assertOk();
        $this->actingAs($cajero)->get('/caja/cerrar/inventario')->assertRedirect('/caja/cerrar');
        // Y el corte se guarda (antes lo rechazaba: "Falta registrar el conteo").
        $this->actingAs($cajero)->postJson('/caja/cerrar', ['fund' => ['matches' => true], 'sales' => ['matches' => true]])
            ->assertOk()->assertJson(['success' => true]);
    }

    public function test_el_mensaje_de_whatsapp_se_puede_cambiar(): void
    {
        $customer = Customer::create(['name' => 'Chuy Pérez', 'phone' => '9511234567']);
        $this->assertStringContainsString(rawurlencode('¡Hola Chuy!'), $customer->whatsappCardUrl());

        BusinessSettings::current()->update(['whatsapp_card_message' => 'Qué tal {nombre}, tu tarjeta: {enlace}']);
        $this->assertStringContainsString(rawurlencode('Qué tal Chuy, tu tarjeta: '.$customer->cardUrl()), $customer->whatsappCardUrl());

        // Sin {enlace}, el enlace se pone al final.
        BusinessSettings::current()->update(['whatsapp_card_message' => 'Hola {nombre}']);
        $this->assertStringContainsString(rawurlencode("Hola Chuy\n\n".$customer->cardUrl()), $customer->whatsappCardUrl());
    }

    public function test_manager_y_gerente_pueden_ir_al_panel_sin_abrir_caja(): void
    {
        $this->actingAs($this->user('gerente', '1111'))->get('/caja/abrir')->assertOk()->assertSee('Ir al panel');
        $this->actingAs($this->user('cajero', '3333'))->get('/caja/abrir')->assertOk()->assertDontSee('Ir al panel');
    }

    public function test_receta_con_varios_insumos_de_un_jalon(): void
    {
        $product = $this->product();
        $manager = $this->user('manager', '2222');
        $papa = Ingredient::create(['name' => 'Papa', 'unit' => 'g', 'stock_qty' => 0, 'cost_per_unit' => 1, 'is_active' => true]);
        $salchicha = Ingredient::create(['name' => 'Salchicha', 'unit' => 'pz', 'stock_qty' => 0, 'cost_per_unit' => 1, 'is_active' => true]);
        $product->recipeItems()->create(['ingredient_id' => $papa->id, 'qty' => 100]);

        $lw = Livewire::actingAs($manager)
            ->test(RecipeItemsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make('addMany')->table())
            ->callAction(TestAction::make('todos')->schemaComponent('pick')->table());

        // "Seleccionar todos" pone un renglón por insumo; sin cantidades no guarda.
        $items = $lw->instance()->mountedActions[0]['data']['items'];
        $this->assertEqualsCanonicalizing([$papa->id, $salchicha->id], array_column($items, 'ingredient_id'));
        $lw->callMountedAction()->assertHasFormErrors();
        $this->assertSame(1, $product->recipeItems()->count());

        $keys = array_keys($items);
        $byId = array_combine(array_column($items, 'ingredient_id'), $keys);
        $lw->set("mountedActions.0.data.items.{$byId[$papa->id]}.qty", 150)
            ->set("mountedActions.0.data.items.{$byId[$salchicha->id]}.qty", 2)
            ->callMountedAction()
            ->assertHasNoFormErrors();

        // La papa que ya estaba se actualiza (no se duplica); la salchicha se agrega.
        $this->assertSame(2, $product->recipeItems()->count());
        $this->assertEquals(150, $product->recipeItems()->where('ingredient_id', $papa->id)->value('qty'));
        $this->assertEquals(2, $product->recipeItems()->where('ingredient_id', $salchicha->id)->value('qty'));
    }

    public function test_el_martes_el_extra_gratis_sale_marcado_solo(): void
    {
        $product = $this->product();
        $otro = Product::create(['category_id' => $product->category_id, 'name' => 'Papas sola', 'base_price' => 50, 'is_active' => true]);
        $extras = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 5, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $salchicha = Modifier::create(['modifier_group_id' => $extras->id, 'name' => 'Salchicha extra', 'price_delta' => 12, 'is_active' => true]);
        $queso = Modifier::create(['modifier_group_id' => $extras->id, 'name' => 'Doble queso', 'price_delta' => 15, 'is_active' => true]);
        $product->modifierGroups()->attach($extras->id);
        $otro->modifierGroups()->attach($extras->id);

        $promo = Promotion::create(['name' => 'Martes de salchicha', 'type' => 'free', 'scope' => 'modifier', 'is_active' => true, 'days_of_week' => [2]]);
        $promo->modifiers()->attach($salchicha->id);
        $promo->products()->attach($product->id, ['qty_required' => 1]); // solo en Salchipapas
        $cajero = $this->user('cajero', '3333');

        $this->travelTo(now()->next('Tuesday')->setTime(13, 0));
        $this->actingAs($cajero)->getJson("/venta/productos/{$product->id}")->assertJsonPath('auto_modifier_ids', [$salchicha->id]);
        $this->actingAs($cajero)->getJson("/venta/productos/{$otro->id}")->assertJsonPath('auto_modifier_ids', []);

        $this->travelTo(now()->next('Wednesday')->setTime(13, 0));
        $this->actingAs($cajero)->getJson("/venta/productos/{$product->id}")->assertJsonPath('auto_modifier_ids', []);
    }

    public function test_despues_de_cobrar_la_siguiente_venta_ya_no_trae_al_cliente(): void
    {
        $product = $this->product();
        $cajero = $this->user('cajero', '3333');
        CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $chuy = Customer::create(['name' => 'Chuy', 'phone' => '9511234567']);

        $this->actingAs($cajero)->get("/venta/cliente/seleccionar/{$chuy->id}"); // como al escanear su QR
        $this->assertSame($chuy->id, session('selected_customer_id'));

        $this->actingAs($cajero)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1]], 'payment_method' => 'efectivo', 'customer_id' => $chuy->id,
        ])->assertOk();

        $this->assertNull(session('selected_customer_id'));
        $this->actingAs($cajero)->get('/venta')->assertOk()->assertDontSee('name: "Chuy"', false);
    }
}
