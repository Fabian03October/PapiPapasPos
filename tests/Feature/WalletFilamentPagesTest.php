<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletFilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager']);

        return User::create([
            'name' => 'Manager',
            'email' => 'manager@test.local',
            'password' => 'password',
            'pin' => '1234',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_registro_qr_page_renders(): void
    {
        $this->actingAs($this->manager())
            ->get('/admin/registro-qr')
            ->assertOk()
            ->assertSee('Código QR de registro')
            ->assertSee('Imprimir')
            ->assertDontSee('Registro Qr');
    }

    public function test_wallet_messages_page_renders(): void
    {
        $this->actingAs($this->manager())
            ->get('/admin/wallet-messages')
            ->assertOk()
            ->assertSee('Mensajes Wallet');
    }

    public function test_wallet_card_design_page_renders(): void
    {
        $this->actingAs($this->manager())
            ->get('/admin/wallet-card-design')
            ->assertOk()
            ->assertSee('Diseño de tarjeta')
            ->assertDontSee('Wallet Card Design');
    }

    public function test_customers_list_page_renders(): void
    {
        $this->actingAs($this->manager())
            ->get('/admin/customers')
            ->assertOk();
    }
}
