<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLoyaltyRedemption;
use App\Models\LoyaltyLevel;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyRewardDeferralTest extends TestCase
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

    private function product(float $price = 100): Product
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Papas',
            'base_price' => $price,
            'is_active' => true,
        ]);
    }

    private function level(): LoyaltyLevel
    {
        return LoyaltyLevel::create([
            'level_number' => 1,
            'discount_percent' => 10,
        ]);
    }

    public function test_al_ganar_un_premio_sin_canjearlo_queda_pendiente_y_la_visita_avanza(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product(100);
        $this->level();
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 3]);

        $response = $this->actingAs($user)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            'redeem_reward' => false,
        ]);

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('total'));

        $customer->refresh();
        $this->assertSame(4, $customer->current_visits);

        $redemption = CustomerLoyaltyRedemption::where('customer_id', $customer->id)->first();
        $this->assertNotNull($redemption);
        $this->assertSame('pending', $redemption->status);
        $this->assertSame('discount', $redemption->type);
        $this->assertNull($redemption->redeemed_at);
        $this->assertTrue($redemption->expires_at->between(now()->addDays(6), now()->addDays(8)));
    }

    public function test_un_premio_pendiente_se_puede_canjear_en_una_venta_posterior(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product(200);
        $level = $this->level();
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 4]);

        $pending = CustomerLoyaltyRedemption::create([
            'customer_id' => $customer->id,
            'loyalty_level_id' => $level->id,
            'type' => 'discount',
            'status' => 'pending',
            'earned_at' => now()->subDays(2),
            'expires_at' => now()->addDays(5),
        ]);

        $response = $this->actingAs($user)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            'redeem_reward' => true,
        ]);

        $response->assertOk();
        // 10% de descuento sobre 200 = 20.
        $this->assertEquals(180.0, $response->json('total'));

        $pending->refresh();
        $this->assertSame('redeemed', $pending->status);
        $this->assertNotNull($pending->redeemed_at);
        $this->assertSame($response->json('sale_id'), $pending->redeemed_sale_id);
        $this->assertSame(20.0, (float) $pending->discount_applied);

        // La visita de ESTA venta también avanzó normal (no era milestone).
        $this->assertSame(5, $customer->fresh()->current_visits);
    }

    public function test_un_premio_pendiente_vencido_ya_no_se_ofrece(): void
    {
        $user = $this->cajero();
        $this->openCashSession($user);
        $product = $this->product(200);
        $level = $this->level();
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5511112222', 'current_level' => 1, 'current_visits' => 4]);

        $expired = CustomerLoyaltyRedemption::create([
            'customer_id' => $customer->id,
            'loyalty_level_id' => $level->id,
            'type' => 'discount',
            'status' => 'pending',
            'earned_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)->postJson('/venta/cobrar', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'efectivo',
            'customer_id' => $customer->id,
            'redeem_reward' => true,
        ]);

        $response->assertOk();
        $this->assertEquals(200.0, $response->json('total'));
        $this->assertNull($response->json('loyalty'));

        $expired->refresh();
        $this->assertSame('pending', $expired->status);
    }
}
