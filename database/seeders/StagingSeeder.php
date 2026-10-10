<?php

namespace Database\Seeders;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos para probar en el entorno staging de Railway sin capturar todo a
 * mano: catálogo parecido al real, un cajero y la caja ya abierta.
 *
 * Solo corre si la variable SEED_STAGING_DATA=true está puesta (ver
 * DatabaseSeeder) - producción nunca la tiene. Se puede correr varias veces
 * (cada deploy corre los seeders): no duplica nada.
 */
class StagingSeeder extends Seeder
{
    public function run(): void
    {
        // Aderezos, salsas, toppings, extras y presentación.
        $this->call(DemoCatalogSeeder::class);

        $groups = ModifierGroup::whereIn('name', ['Aderezos', 'Salsas', 'Toppings', 'Extras', 'Presentación'])->pluck('id');

        $papas = Category::firstOrCreate(['name' => 'Papas'], ['sort_order' => 1, 'is_active' => true]);
        $ordenes = Category::firstOrCreate(['name' => 'Ordenes'], ['sort_order' => 2, 'is_active' => true]);
        $bebidas = Category::firstOrCreate(['name' => 'Bebidas'], ['sort_order' => 3, 'is_active' => true]);
        $promo = Category::firstOrCreate(['name' => 'Papis Promo'], ['sort_order' => 4, 'is_active' => true]);

        foreach ([['Papis', 69], ['Salchi-papis', 89], ['Papi-rings', 99]] as [$name, $price]) {
            $this->product($papas, $name, $price, kitchen: true)->modifierGroups()->syncWithoutDetaching($groups);
        }

        $this->product($ordenes, 'Orden de nuggets', 85, kitchen: true);
        $this->product($ordenes, 'Orden de alitas', 120, kitchen: true);
        $this->product($promo, 'Combo Papis + Refresco', 95, kitchen: true)->modifierGroups()->syncWithoutDetaching($groups);

        $refresco = $this->product($bebidas, 'Refrescos', 35, kitchen: false);
        foreach (['Coca', 'Sprite', 'Fanta'] as $flavor) {
            ProductVariant::firstOrCreate(['product_id' => $refresco->id, 'name' => $flavor], ['price_delta' => 0, 'is_active' => true]);
        }
        $this->product($bebidas, 'Agua', 25, kitchen: false);

        $cajero = User::firstOrCreate(['email' => 'cajero@staging.test'], [
            'name' => 'Cajero Staging',
            'password' => str()->random(32),
            'pin' => '1111',
            'role_id' => Role::where('name', 'cajero')->value('id'),
            'is_active' => true,
            'must_change_pin' => false,
        ]);

        if (! CashSession::open()) {
            CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 500, 'opened_at' => now()]);
        }
    }

    protected function product(Category $category, string $name, float $price, bool $kitchen): Product
    {
        return Product::firstOrCreate(['name' => $name], [
            'category_id' => $category->id,
            'base_price' => $price,
            'prints_to_kitchen' => $kitchen,
            'is_active' => true,
        ]);
    }
}
