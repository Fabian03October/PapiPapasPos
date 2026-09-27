<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_promotion_page_does_not_show_untranslated_products_label(): void
    {
        $role = Role::firstOrCreate(['name' => 'manager']);
        $user = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/promotions/create')
            ->assertOk()
            ->assertDontSee('>Products<', false);
    }
}
