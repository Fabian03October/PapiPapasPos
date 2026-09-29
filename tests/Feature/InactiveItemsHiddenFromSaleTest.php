<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveItemsHiddenFromSaleTest extends TestCase
{
    use RefreshDatabase;

    private function cajero(): User
    {
        $role = Role::firstOrCreate(['name' => 'cajero']);

        return User::create([
            'name' => 'Cajero', 'email' => 'cajero@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    public function test_variantes_y_modificadores_inactivos_no_aparecen_en_la_venta(): void
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Papas', 'base_price' => 50, 'is_active' => true]);

        $activeVariant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Chica', 'price_delta' => 0, 'is_active' => true]);
        ProductVariant::create(['product_id' => $product->id, 'name' => 'Vieja', 'price_delta' => 0, 'is_active' => false]);

        $group = ModifierGroup::create(['name' => 'Salsas', 'min_select' => 0, 'max_select' => 3, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $activeModifier = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Valentina', 'price_delta' => 0, 'is_active' => true]);
        Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Descontinuada', 'price_delta' => 0, 'is_active' => false]);
        $product->modifierGroups()->attach($group->id);

        $inactiveGroup = ModifierGroup::create(['name' => 'Grupo viejo', 'min_select' => 0, 'max_select' => 1, 'is_required' => false, 'sort_order' => 2, 'is_active' => false]);
        Modifier::create(['modifier_group_id' => $inactiveGroup->id, 'name' => 'X', 'price_delta' => 0, 'is_active' => true]);
        $product->modifierGroups()->attach($inactiveGroup->id);

        $response = $this->actingAs($this->cajero())->getJson("/venta/productos/{$product->id}");

        $response->assertOk();
        $variantIds = collect($response->json('variants'))->pluck('id');
        $this->assertTrue($variantIds->contains($activeVariant->id));
        $this->assertSame(1, $variantIds->count());

        $groups = collect($response->json('modifier_groups'));
        $this->assertSame(1, $groups->count());
        $modifierIds = collect($groups->first()['modifiers'])->pluck('id');
        $this->assertTrue($modifierIds->contains($activeModifier->id));
        $this->assertSame(1, $modifierIds->count());
    }

    public function test_un_cliente_inactivo_no_se_encuentra_al_buscarlo_ni_escanearlo(): void
    {
        $active = Customer::create(['name' => 'Ana Activa', 'phone' => '5511112222', 'is_active' => true]);
        $inactive = Customer::create(['name' => 'Ana Inactiva', 'phone' => '5511113333', 'is_active' => false]);

        $user = $this->cajero();

        $search = $this->actingAs($user)->getJson('/venta/clientes/buscar?q=Ana');
        $ids = collect($search->json())->pluck('id');
        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($inactive->id));

        $this->actingAs($user)
            ->getJson('/venta/clientes/qr/' . $inactive->qr_code)
            ->assertStatus(404);

        $this->actingAs($user)
            ->getJson('/venta/clientes/qr/' . $active->qr_code)
            ->assertOk()
            ->assertJson(['found' => true]);
    }
}
