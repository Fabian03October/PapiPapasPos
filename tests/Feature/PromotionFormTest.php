<?php

namespace Tests\Feature;

use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionFormTest extends TestCase
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

    public function test_create_promotion_page_does_not_show_untranslated_products_label(): void
    {
        $this->actingAs($this->manager())
            ->get('/admin/promotions/create')
            ->assertOk()
            ->assertDontSee('>Products<', false);
    }

    /**
     * Regresión real: guardar una promoción con alcance "extra/modificador"
     * desde el panel no dejaba nada en la venta ni en el ticket - resultó
     * ser que CreatePromotion/EditPromotion solo sincronizaban "products"
     * al guardar, nunca "modifiers" (aunque el campo se viera lleno en el
     * formulario, nunca llegaba a la base de datos).
     */
    public function test_crear_promocion_con_alcance_modificador_de_verdad_guarda_el_modificador(): void
    {
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);
        Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 89, 'is_active' => true]);
        $group = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 5, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $modifier = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Extra salchipulpos', 'price_delta' => 20, 'is_active' => true]);

        Livewire::actingAs($this->manager())
            ->test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Extra salchipulpos gratis',
                'type' => 'free',
                'scope' => 'modifier',
                'is_active' => true,
                'modifiers' => [
                    ['modifier_id' => $modifier->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $promotion = Promotion::where('name', 'Extra salchipulpos gratis')->firstOrFail();
        $this->assertTrue($promotion->modifiers->contains($modifier->id));
    }

    public function test_editar_promocion_conserva_y_actualiza_los_modificadores(): void
    {
        $group = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 5, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $modifierA = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Salchipulpos', 'price_delta' => 20, 'is_active' => true]);
        $modifierB = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Queso extra', 'price_delta' => 10, 'is_active' => true]);

        $promotion = Promotion::create(['name' => 'Extra gratis', 'type' => 'free', 'scope' => 'modifier', 'is_active' => true]);
        $promotion->modifiers()->attach($modifierA->id);

        Livewire::actingAs($this->manager())
            ->test(EditPromotion::class, ['record' => $promotion->getRouteKey()])
            ->fillForm([
                'modifiers' => [
                    ['modifier_id' => $modifierB->id],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $promotion->refresh();
        $this->assertTrue($promotion->modifiers->contains($modifierB->id));
        $this->assertFalse($promotion->modifiers->contains($modifierA->id));
    }
}
