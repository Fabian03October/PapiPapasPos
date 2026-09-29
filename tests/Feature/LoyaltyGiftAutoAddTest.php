<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\Ingredient;
use App\Models\LoyaltyLevel;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión pedida por el dueño: antes, si el cajero decía "sí" al premio
 * de fidelidad de "producto gratis" (visita 8) pero se le olvidaba agregar
 * a mano ese producto al carrito, el descuento se quedaba en $0 y el
 * cliente no se llevaba su premio ni aparecía en el ticket - sin que nadie
 * se diera cuenta. Ahora el sistema lo agrega solo.
 */
class LoyaltyGiftAutoAddTest extends TestCase
{
    use RefreshDatabase;

    private function cajero(): User
    {
        $role = Role::firstOrCreate(['name' => 'cajero']);

        return User::create([
            'name' => 'Cajero', 'email' => 'cajero+'.uniqid().'@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function openCashSession(User $user): CashSession
    {
        return CashSession::create([
            'user_id' => $user->id,
            'opening_amount' => 0,
            'opened_at' => now(),
        ]);
    }

    private function product(string $name, float $price): Product
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'base_price' => $price,
            'is_active' => true,
        ]);
    }

    public function test_premio_de_visita_8_se_agrega_solo_al_carrito_y_se_descuenta_si_no_estaba(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product('Salchipapas', 89);
        $giftProduct = $this->product('Agua', 25);

        $level = LoyaltyLevel::create([
            'level_number' => 1,
            'discount_percent' => 10,
            'free_product_id' => $giftProduct->id,
            'gift_description' => 'Agua gratis',
        ]);

        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 7]);

        // El cajero solo puso la salchipapa - el agua (premio) NO va en el carrito.
        $response = $this->actingAs($user)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            'redeem_reward' => true,
        ]);

        $response->assertOk();
        // 89 (salchipapa) + 25 (agua agregada sola) - 25 (premio gratis) = 89.
        $this->assertEquals(89.0, $response->json('total'));

        $sale = Sale::findOrFail($response->json('sale_id'));
        $sale->loadMissing('items.product');
        $this->assertTrue($sale->items->pluck('product.name')->contains('Agua'));

        $redemption = CustomerLoyaltyRedemption::where('customer_id', $customer->id)->first();
        $this->assertSame('redeemed', $redemption->status);
        $this->assertSame(25.0, (float) $redemption->discount_applied);
    }

    public function test_si_el_cajero_ya_puso_el_premio_en_el_carrito_no_se_duplica(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product('Salchipapas', 89);
        $giftProduct = $this->product('Agua', 25);

        LoyaltyLevel::create([
            'level_number' => 1,
            'discount_percent' => 10,
            'free_product_id' => $giftProduct->id,
            'gift_description' => 'Agua gratis',
        ]);

        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 7]);

        $response = $this->actingAs($user)->postJson('/venta/cobrar', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
                ['product_id' => $giftProduct->id, 'qty' => 1],
            ],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            'redeem_reward' => true,
        ]);

        $response->assertOk();
        $this->assertEquals(89.0, $response->json('total'));

        $sale = Sale::findOrFail($response->json('sale_id'));
        $this->assertSame(2, $sale->items()->count());
    }

    public function test_si_dice_no_al_premio_no_se_agrega_nada(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product('Salchipapas', 89);
        $giftProduct = $this->product('Agua', 25);

        LoyaltyLevel::create([
            'level_number' => 1,
            'discount_percent' => 10,
            'free_product_id' => $giftProduct->id,
            'gift_description' => 'Agua gratis',
        ]);

        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 7]);

        $response = $this->actingAs($user)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            'redeem_reward' => false,
        ]);

        $response->assertOk();
        $this->assertEquals(89.0, $response->json('total'));

        $sale = Sale::findOrFail($response->json('sale_id'));
        $this->assertSame(1, $sale->items()->count());
    }

    public function test_el_insumo_del_premio_agregado_solo_tambien_se_descuenta_del_inventario(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product('Salchipapas', 89);
        $giftProduct = $this->product('Agua', 25);
        $ingredient = Ingredient::create(['name' => 'Botella de agua', 'unit' => 'pza', 'stock_qty' => 10, 'min_stock' => 1, 'cost_per_unit' => 5]);
        RecipeItem::create(['product_id' => $giftProduct->id, 'variant_id' => null, 'ingredient_id' => $ingredient->id, 'qty' => 1]);

        LoyaltyLevel::create([
            'level_number' => 1,
            'discount_percent' => 10,
            'free_product_id' => $giftProduct->id,
            'gift_description' => 'Agua gratis',
        ]);

        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 7]);

        $response = $this->actingAs($user)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            'redeem_reward' => true,
        ]);

        $response->assertOk();
        $this->assertEquals(9.0, $ingredient->fresh()->stock_qty);
    }

    public function test_preview_tambien_refleja_el_premio_agregado_solo_sin_guardar_nada(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product('Salchipapas', 89);
        $giftProduct = $this->product('Agua', 25);

        LoyaltyLevel::create([
            'level_number' => 1,
            'discount_percent' => 10,
            'free_product_id' => $giftProduct->id,
            'gift_description' => 'Agua gratis',
        ]);

        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 7]);

        $response = $this->actingAs($user)->postJson('/venta/promociones/preview', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'customer_id' => $customer->id,
            'redeem_reward' => true,
        ]);

        $response->assertOk();
        $this->assertEquals(89.0, $response->json('total'));
        $this->assertSame('Agua', $response->json('auto_added_gift.name'));
        $this->assertTrue($response->json('loyalty.free_product_in_cart'));

        // No debe haber guardado nada - es solo una vista previa.
        $this->assertSame(0, Sale::count());
    }
}
