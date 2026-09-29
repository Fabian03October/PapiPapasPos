<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InactiveRecordsHiddenByDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager']);

        return User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    public function test_productos_inactivos_se_esconden_por_default_y_se_pueden_mostrar(): void
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);

        $active = Product::create([
            'category_id' => $category->id, 'name' => 'Papas activas', 'base_price' => 50, 'is_active' => true,
        ]);
        $inactive = Product::create([
            'category_id' => $category->id, 'name' => 'Papas viejas', 'base_price' => 50, 'is_active' => false,
        ]);

        Livewire::actingAs($this->manager())
            ->test(ListProducts::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive])
            ->filterTable('is_active', null)
            ->assertCanSeeTableRecords([$active, $inactive]);
    }
}
