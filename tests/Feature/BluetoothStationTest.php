<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estación Bluetooth (impresora-bt.js): la impresora acepta un solo
 * dispositivo, así que el conectado imprime también las ventas de los demás.
 */
class BluetoothStationTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private CashSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = User::create([
            'name' => 'Cajero', 'email' => 'cajero@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => Role::firstOrCreate(['name' => 'cajero'])->id, 'is_active' => true,
        ]);
        $this->session = CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 0, 'opened_at' => now()->subHours(3)]);
    }

    private function sale(string $folio, $createdAt): Sale
    {
        $sale = Sale::create([
            'folio' => $folio, 'user_id' => $this->cajero->id, 'cash_session_id' => $this->session->id, 'status' => 'pagada',
            'subtotal' => 50, 'discount' => 0, 'total' => 50, 'payment_method' => 'efectivo',
        ]);
        $sale->created_at = $createdAt;
        $sale->save();

        return $sale;
    }

    public function test_solo_toma_las_ventas_hechas_desde_que_se_activo_la_estacion(): void
    {
        $this->sale('0001', now()->subHours(2));
        $this->sale('0002', now()->subMinutes(10));
        $this->sale('0003', now()->subMinute());

        // El navegador manda la fecha en UTC (toISOString); las ventas se
        // guardan en hora de México. Con la conversión mal, no saldría
        // ninguna (o saldrían todas).
        $desde = now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s.v\Z');

        $folios = collect($this->actingAs($this->cajero)->getJson('/impresion/pendientes?desde=' . urlencode($desde))->json('sales'))
            ->pluck('folio')->all();

        $this->assertSame(['0003'], $folios);
    }

    public function test_sin_desde_la_estacion_de_la_compu_sigue_viendo_todo_el_turno(): void
    {
        $this->sale('0001', now()->subHours(2));
        $this->sale('0002', now()->subMinute());

        $this->actingAs($this->cajero)->getJson('/impresion/pendientes')->assertJsonCount(2, 'sales');
    }

    public function test_si_falla_la_impresion_la_venta_se_libera_para_reintentarse(): void
    {
        $sale = $this->sale('0001', now());

        $this->actingAs($this->cajero)->postJson("/impresion/{$sale->id}/marcar")->assertJson(['claimed' => true]);
        $this->actingAs($this->cajero)->getJson('/impresion/pendientes')->assertJsonCount(0, 'sales');

        $this->actingAs($this->cajero)->postJson("/impresion/{$sale->id}/liberar")->assertOk();

        $this->assertNull($sale->fresh()->printed_at);
        $this->actingAs($this->cajero)->getJson('/impresion/pendientes')->assertJsonCount(1, 'sales');
        $this->actingAs($this->cajero)->postJson("/impresion/{$sale->id}/marcar")->assertJson(['claimed' => true]);
    }
}
