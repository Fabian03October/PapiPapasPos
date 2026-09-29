<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El caso real reportado: producto $89 + un extra de $20. La promoción
     * es "gratis" sobre el EXTRA (scope=modifier) - antes de este rediseño
     * era facilísimo mal-configurarla y que descontara los $109 completos
     * en vez de solo los $20 del extra.
     */
    public function test_promocion_de_extra_gratis_solo_descuenta_el_extra_no_todo_el_producto(): void
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);

        $group = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 5, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $modifier = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Extra salchipulpos', 'price_delta' => 20, 'is_active' => true]);

        $promo = Promotion::create([
            'name' => 'Martes extra salchipulpos gratis',
            'type' => 'free',
            'scope' => 'modifier',
            'is_active' => true,
        ]);
        $promo->modifiers()->attach($modifier->id);

        $itemsData = [[
            'product_id' => $product->id,
            'variant_id' => null,
            'qty' => 1,
            'unit_price' => 109.0,
            'line_total' => 109.0,
            'modifiers' => collect([$modifier]),
        ]];

        $result = (new PromotionService)->evaluate($itemsData);

        $this->assertSame(20.0, $result['discount']);
        $this->assertSame(['Martes extra salchipulpos gratis'], $result['applied']);
    }

    public function test_promocion_de_producto_gratis_sigue_descontando_el_producto_completo(): void
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Agua', 'base_price' => 25, 'is_active' => true]);

        $promo = Promotion::create([
            'name' => 'Agua gratis',
            'type' => 'free',
            'scope' => 'product',
            'is_active' => true,
        ]);
        $promo->products()->attach($product->id, ['qty_required' => 1]);

        $itemsData = [[
            'product_id' => $product->id,
            'variant_id' => null,
            'qty' => 1,
            'unit_price' => 25.0,
            'line_total' => 25.0,
            'modifiers' => collect(),
        ]];

        $result = (new PromotionService)->evaluate($itemsData);

        $this->assertSame(25.0, $result['discount']);
    }

    public function test_descuento_por_porcentaje_sobre_un_extra_especifico(): void
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);

        $group = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 5, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $modifier = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Extra queso', 'price_delta' => 10, 'is_active' => true]);

        $promo = Promotion::create([
            'name' => '50% en extra de queso',
            'type' => 'discount',
            'scope' => 'modifier',
            'discount_mode' => 'percent',
            'percent_value' => 50,
            'is_active' => true,
        ]);
        $promo->modifiers()->attach($modifier->id);

        $itemsData = [[
            'product_id' => $product->id,
            'variant_id' => null,
            'qty' => 1,
            'unit_price' => 99.0,
            'line_total' => 99.0,
            'modifiers' => collect([$modifier]),
        ]];

        $result = (new PromotionService)->evaluate($itemsData);

        $this->assertSame(5.0, $result['discount']);
    }
}
