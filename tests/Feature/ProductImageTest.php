<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
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

    private function producto(array $attributes = []): Product
    {
        $category = Category::create(['name' => 'Bebidas', 'sort_order' => 1, 'is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id, 'name' => 'Refresco', 'base_price' => 35, 'is_active' => true,
        ], $attributes));
    }

    public function test_la_foto_aparece_en_el_boton_de_la_venta(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('coca.jpg')->store('products', 'public');
        $this->producto(['image_path' => $path]);

        $cajero = $this->cajero();
        CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);

        $this->actingAs($cajero)->get('/venta')
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false);
    }

    public function test_sin_foto_se_ve_el_icono_de_siempre(): void
    {
        $this->producto();

        $cajero = $this->cajero();
        CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);

        $this->actingAs($cajero)->get('/venta')
            ->assertOk()
            ->assertDontSee('object-cover', false);
    }

    public function test_reemplazar_o_borrar_el_producto_elimina_la_foto_anterior(): void
    {
        Storage::fake('public');
        $old = UploadedFile::fake()->image('vieja.jpg')->store('products', 'public');
        $new = UploadedFile::fake()->image('nueva.jpg')->store('products', 'public');
        $product = $this->producto(['image_path' => $old]);

        $product->update(['image_path' => $new]);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);

        $product->delete();
        Storage::disk('public')->assertMissing($new);
    }
}
