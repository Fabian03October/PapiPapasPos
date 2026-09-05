<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Categorías
        $antojitos = Category::firstOrCreate(['name' => 'Antojitos'], ['sort_order' => 1, 'is_active' => true]);
        $bebidas = Category::firstOrCreate(['name' => 'Bebidas'], ['sort_order' => 2, 'is_active' => true]);

        // Grupos de modificadores (con su orden de aparición)
        $aderezos = ModifierGroup::firstOrCreate(['name' => 'Aderezos'], [
            'min_select' => 0, 'max_select' => 3, 'is_required' => false, 'sort_order' => 1,
        ]);
        $salsas = ModifierGroup::firstOrCreate(['name' => 'Salsas'], [
            'min_select' => 0, 'max_select' => 2, 'is_required' => false, 'sort_order' => 2,
        ]);
        $toppings = ModifierGroup::firstOrCreate(['name' => 'Toppings'], [
            'min_select' => 0, 'max_select' => 3, 'is_required' => false, 'sort_order' => 3,
        ]);
        $extras = ModifierGroup::firstOrCreate(['name' => 'Extras'], [
            'min_select' => 0, 'max_select' => 5, 'is_required' => false, 'sort_order' => 4,
        ]);
        $presentacion = ModifierGroup::firstOrCreate(['name' => 'Presentación'], [
            'min_select' => 1, 'max_select' => 1, 'is_required' => true, 'sort_order' => 5,
        ]);

        // Modificadores dentro de cada grupo
        foreach (['Catsup', 'Mostaza', 'Mayonesa'] as $name) {
            Modifier::firstOrCreate(['name' => $name, 'modifier_group_id' => $aderezos->id], ['price_delta' => 0]);
        }
        foreach (['Valentina', 'BBQ picante', 'Habanero'] as $name) {
            Modifier::firstOrCreate(['name' => $name, 'modifier_group_id' => $salsas->id], ['price_delta' => 0]);
        }
        foreach (['Chetos', 'Queso gratinado', 'Tocino'] as $name) {
            Modifier::firstOrCreate(['name' => $name, 'modifier_group_id' => $toppings->id], ['price_delta' => 0]);
        }
        Modifier::firstOrCreate(['name' => 'Salchicha extra', 'modifier_group_id' => $extras->id], ['price_delta' => 12]);
        Modifier::firstOrCreate(['name' => 'Doble queso', 'modifier_group_id' => $extras->id], ['price_delta' => 15]);
        Modifier::firstOrCreate(['name' => 'Plato', 'modifier_group_id' => $presentacion->id], ['price_delta' => 0]);
        Modifier::firstOrCreate(['name' => 'Domo', 'modifier_group_id' => $presentacion->id], ['price_delta' => 8]);

        // Productos
        $salchipapas = Product::firstOrCreate(['name' => 'Salchipapas'], [
            'category_id' => $antojitos->id,
            'base_price' => 55,
            'prints_to_kitchen' => true,
            'is_active' => true,
        ]);
        $salchipapas->modifierGroups()->syncWithoutDetaching([
            $aderezos->id, $salsas->id, $toppings->id, $extras->id, $presentacion->id,
        ]);

        Product::firstOrCreate(['name' => 'Café'], [
            'category_id' => $bebidas->id,
            'base_price' => 32,
            'prints_to_kitchen' => false,
            'is_active' => true,
        ]);

        Product::firstOrCreate(['name' => 'Limonada'], [
            'category_id' => $bebidas->id,
            'base_price' => 35,
            'prints_to_kitchen' => false,
            'is_active' => true,
        ]);
    }
}