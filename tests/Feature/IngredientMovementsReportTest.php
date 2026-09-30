<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido del dueño: ver en el Dashboard/reportes cuánto insumo entró
 * (compras), cuánto salió por venta, y cuánta merma hubo en el periodo -
 * reusando los movimientos que ya se registran solos (compra, venta,
 * merma, ajuste), sin capturar nada aparte.
 */
class IngredientMovementsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_entradas_salidas_y_merma_por_insumo_en_el_periodo(): void
    {
        $role = Role::firstOrCreate(['name' => 'manager']);
        $user = User::create(['name' => 'Manager', 'email' => 'manager+'.uniqid().'@test.local', 'password' => 'password', 'pin' => '1234', 'role_id' => $role->id, 'is_active' => true]);
        $ingredient = Ingredient::create(['name' => 'Papa', 'unit' => 'kg', 'stock_qty' => 10, 'min_stock' => 1, 'cost_per_unit' => 20, 'is_active' => true]);

        // Entrada de 5kg.
        InventoryMovement::create(['ingredient_id' => $ingredient->id, 'type' => 'compra', 'qty' => 5, 'unit_cost' => 20, 'user_id' => $user->id]);
        // Se vendieron 3kg (se guarda en negativo).
        InventoryMovement::create(['ingredient_id' => $ingredient->id, 'type' => 'venta', 'qty' => -3, 'unit_cost' => 20, 'user_id' => $user->id]);
        // 1kg de merma (también negativo).
        InventoryMovement::create(['ingredient_id' => $ingredient->id, 'type' => 'merma', 'qty' => -1, 'unit_cost' => 20, 'user_id' => $user->id]);
        // Un ajuste no debe contar como entrada, salida ni merma.
        InventoryMovement::create(['ingredient_id' => $ingredient->id, 'type' => 'ajuste', 'qty' => 2, 'unit_cost' => 20, 'user_id' => $user->id]);
        // Movimiento de ayer: fuera del periodo de hoy, no debe contarse.
        $yesterday = InventoryMovement::create(['ingredient_id' => $ingredient->id, 'type' => 'compra', 'qty' => 100, 'unit_cost' => 20, 'user_id' => $user->id]);
        $yesterday->forceFill(['created_at' => now()->subDay()])->save();

        $service = ReportMetricsService::fromFilters(['range' => 'today']);
        $row = $service->ingredientMovements()->firstWhere('id', $ingredient->id);

        $this->assertEquals(5.0, (float) $row->entradas);
        $this->assertEquals(3.0, (float) $row->salidas);
        $this->assertEquals(1.0, (float) $row->merma);
    }

    public function test_no_incluye_insumos_inactivos(): void
    {
        Ingredient::create(['name' => 'Insumo viejo', 'unit' => 'pza', 'stock_qty' => 0, 'min_stock' => 0, 'cost_per_unit' => 1, 'is_active' => false]);

        $service = ReportMetricsService::fromFilters(['range' => 'today']);

        $this->assertTrue($service->ingredientMovements()->where('name', 'Insumo viejo')->isEmpty());
    }
}
