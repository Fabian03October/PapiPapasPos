<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WalletMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comando pedido por el dueño antes de operar de verdad: borrar ventas,
 * clientes, turnos y mensajes de prueba, SIN tocar el catálogo (productos,
 * insumos, recetas) ni la configuración, y conservando solo la cuenta de
 * usuario que él elija en el momento.
 */
class LimpiarDatosPruebaTest extends TestCase
{
    use RefreshDatabase;

    public function test_borra_datos_de_prueba_y_conserva_catalogo_insumos_y_el_usuario_elegido(): void
    {
        $role = Role::firstOrCreate(['name' => 'manager']);
        $keep = User::create(['name' => 'Fabian', 'email' => 'fabian@papispapas.test', 'password' => 'password', 'pin' => '1234', 'role_id' => $role->id, 'is_active' => true]);
        $otherRole = Role::firstOrCreate(['name' => 'cajero']);
        $toDelete = User::create(['name' => 'Cajero prueba', 'email' => 'cajero@papispapas.test', 'password' => 'password', 'pin' => '5678', 'role_id' => $otherRole->id, 'is_active' => true]);

        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);
        $ingredient = Ingredient::create(['name' => 'Papa', 'unit' => 'kg', 'stock_qty' => 50, 'min_stock' => 5, 'cost_per_unit' => 20, 'is_active' => true]);
        RecipeItem::create(['product_id' => $product->id, 'variant_id' => null, 'ingredient_id' => $ingredient->id, 'qty' => 0.2]);

        $customer = Customer::create(['name' => 'Cliente de prueba', 'phone' => '5511112222']);
        WalletMessage::create(['title' => 'Prueba', 'body' => 'Hola', 'audience' => 'individual', 'customer_id' => $customer->id, 'notify' => false, 'status' => 'sent', 'sent_by_user_id' => $keep->id]);

        $cashSession = CashSession::create(['user_id' => $toDelete->id, 'opening_amount' => 100, 'opened_at' => now()]);
        $sale = Sale::create([
            'folio' => '0001', 'user_id' => $toDelete->id, 'cash_session_id' => $cashSession->id, 'customer_id' => $customer->id,
            'status' => 'pagada', 'subtotal' => 89, 'discount' => 0, 'total' => 89, 'payment_method' => 'efectivo',
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'qty' => 1, 'unit_price' => 89, 'line_total' => 89]);
        InventoryMovement::create(['ingredient_id' => $ingredient->id, 'type' => 'venta', 'qty' => -0.2, 'unit_cost' => 20, 'user_id' => $toDelete->id]);

        $this->artisan('app:limpiar-datos-prueba')
            ->expectsQuestion('¿Cuál correo quieres CONSERVAR? (todos los demás usuarios se borran)', 'fabian@papispapas.test')
            ->expectsQuestion('Escribe SI (mayúsculas) para confirmar', 'SI')
            ->assertExitCode(0);

        // Se borró todo lo transaccional/de prueba.
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItem::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, CashSession::count());
        $this->assertSame(0, WalletMessage::count());
        $this->assertSame(0, InventoryMovement::count());

        // Solo queda el usuario elegido.
        $this->assertSame(1, User::count());
        $this->assertSame('fabian@papispapas.test', User::first()->email);

        // El catálogo y las recetas siguen intactos.
        $this->assertSame(1, Product::count());
        $this->assertSame(1, Category::count());
        $this->assertSame(1, RecipeItem::count());
        $this->assertTrue(RecipeItem::first()->ingredient_id === $ingredient->id);

        // El insumo sigue existiendo, pero con el stock en 0.
        $this->assertSame(1, Ingredient::count());
        $this->assertEquals(0, $ingredient->fresh()->stock_qty);
    }

    public function test_se_cancela_si_no_escribe_si_exacto(): void
    {
        $role = Role::firstOrCreate(['name' => 'manager']);
        User::create(['name' => 'Fabian', 'email' => 'fabian@papispapas.test', 'password' => 'password', 'pin' => '1234', 'role_id' => $role->id, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Cliente de prueba', 'phone' => '5511112222']);

        $this->artisan('app:limpiar-datos-prueba')
            ->expectsQuestion('¿Cuál correo quieres CONSERVAR? (todos los demás usuarios se borran)', 'fabian@papispapas.test')
            ->expectsQuestion('Escribe SI (mayúsculas) para confirmar', 'no')
            ->assertExitCode(1);

        $this->assertSame(1, Customer::count());
    }

    public function test_se_cancela_si_el_correo_no_existe(): void
    {
        $role = Role::firstOrCreate(['name' => 'manager']);
        User::create(['name' => 'Fabian', 'email' => 'fabian@papispapas.test', 'password' => 'password', 'pin' => '1234', 'role_id' => $role->id, 'is_active' => true]);

        $this->artisan('app:limpiar-datos-prueba')
            ->expectsQuestion('¿Cuál correo quieres CONSERVAR? (todos los demás usuarios se borran)', 'no-existe@papispapas.test')
            ->assertExitCode(1);

        $this->assertSame(1, User::count());
    }
}
