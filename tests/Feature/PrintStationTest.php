<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintStationTest extends TestCase
{
    use RefreshDatabase;

    private function cajero(): User
    {
        $role = Role::firstOrCreate(['name' => 'cajero']);

        return User::create([
            'name' => 'Cajero',
            'email' => 'cajero@test.local',
            'password' => 'password',
            'pin' => '1234',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function saleInOpenSession(User $user): Sale
    {
        $session = CashSession::create([
            'user_id' => $user->id,
            'opening_amount' => 0,
            'opened_at' => now(),
        ]);

        return Sale::create([
            'folio' => 'T-1',
            'user_id' => $user->id,
            'cash_session_id' => $session->id,
            'status' => 'pagada',
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'payment_method' => 'efectivo',
        ]);
    }

    public function test_pending_solo_regresa_ventas_pagadas_sin_imprimir(): void
    {
        $user = $this->cajero();
        $sale = $this->saleInOpenSession($user);

        $response = $this->actingAs($user)->getJson('/impresion/pendientes');

        $response->assertOk();
        $this->assertSame([$sale->id], collect($response->json('sales'))->pluck('id')->all());
    }

    public function test_marcar_reclama_la_venta_una_sola_vez(): void
    {
        $user = $this->cajero();
        $sale = $this->saleInOpenSession($user);

        // Primer intento: la reclama y la marca como impresa.
        $first = $this->actingAs($user)->postJson("/impresion/{$sale->id}/marcar");
        $first->assertOk()->assertJson(['success' => true, 'claimed' => true]);

        // Segundo intento (simula otra pestaña/dispositivo revisando al mismo
        // tiempo): ya no debe poder reclamarla, así se evita el doble ticket.
        $second = $this->actingAs($user)->postJson("/impresion/{$sale->id}/marcar");
        $second->assertOk()->assertJson(['success' => true, 'claimed' => false]);

        $this->assertNotNull($sale->fresh()->printed_at);
    }

    public function test_pending_ya_no_incluye_una_venta_reclamada(): void
    {
        $user = $this->cajero();
        $sale = $this->saleInOpenSession($user);

        $this->actingAs($user)->postJson("/impresion/{$sale->id}/marcar");

        $response = $this->actingAs($user)->getJson('/impresion/pendientes');

        $this->assertSame([], $response->json('sales'));
    }
}
