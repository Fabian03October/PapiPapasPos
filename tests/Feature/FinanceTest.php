<?php

namespace Tests\Feature;

use App\Exports\ReportFinanceSheet;
use App\Filament\Resources\GeneralMovements\GeneralMovementResource;
use App\Filament\Resources\GeneralMovements\Pages\ManageGeneralMovements;
use App\Filament\Widgets\FinanceStatsOverview;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\ExpenseCategory;
use App\Models\GeneralMovement;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\ReportMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Gastos e ingresos: de caja y generales, por categoría, dashboard y reportes. */
class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private CashSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::create(['name' => 'Gerente', 'email' => 'm@test.local', 'password' => 'x', 'pin' => '1234',
            'role_id' => Role::firstOrCreate(['name' => 'manager'])->id, 'is_active' => true]);
        $this->session = CashSession::create(['user_id' => $this->manager->id, 'opening_amount' => 500, 'opened_at' => now()]);
    }

    private function cat(string $name): int
    {
        return ExpenseCategory::where('name', $name)->value('id');
    }

    private function escenario(): void
    {
        Sale::create(['folio' => '0001', 'user_id' => $this->manager->id, 'cash_session_id' => $this->session->id, 'status' => 'pagada',
            'subtotal' => 1000, 'discount' => 0, 'total' => 1000, 'payment_method' => 'efectivo']);
        CashMovement::create(['cash_session_id' => $this->session->id, 'type' => 'gasto', 'amount' => 50, 'reason' => 'Hielo',
            'expense_category_id' => $this->cat('Insumos'), 'user_id' => $this->manager->id]);
        CashMovement::create(['cash_session_id' => $this->session->id, 'type' => 'ingreso', 'amount' => 20, 'reason' => 'Cambio',
            'user_id' => $this->manager->id]);
        GeneralMovement::create(['type' => 'gasto', 'amount' => 300, 'expense_category_id' => $this->cat('Renta'),
            'description' => 'Renta', 'occurred_on' => now()->toDateString(), 'user_id' => $this->manager->id]);
        GeneralMovement::create(['type' => 'ingreso', 'amount' => 100, 'expense_category_id' => $this->cat('Otros'),
            'description' => 'Venta de cajas', 'occurred_on' => now()->toDateString(), 'user_id' => $this->manager->id]);
    }

    public function test_vienen_las_categorias_iniciales(): void
    {
        $this->assertSame(['Renta', 'Servicios', 'Insumos', 'Nómina', 'Mantenimiento', 'Otros'], array_values(ExpenseCategory::options()));
    }

    public function test_ingresos_gastos_por_categoria_y_ganancia(): void
    {
        $this->escenario();

        $f = ReportMetricsService::fromFilters(['range' => 'today'])->finances();

        $this->assertEquals(['ingresos' => 20, 'gastos' => 50], $f['caja']);
        $this->assertEquals(['ingresos' => 100, 'gastos' => 300], $f['general']);
        $this->assertEquals(1000, $f['ventas']);
        $this->assertEquals(650, $f['ganancia']);                       // 1000 − (50 + 300)
        $this->assertEquals(['Renta' => 300, 'Insumos' => 50], $f['por_categoria']);
        $this->assertCount(1, $f['turnos']);
        $this->assertEquals(50, $f['turnos'][0]['gastos']);
    }

    public function test_el_gasto_de_caja_sin_categoria_queda_en_otros(): void
    {
        $this->actingAs($this->manager)->postJson('/caja/movimientos', ['type' => 'gasto', 'amount' => 30, 'reason' => 'Bolsas'])->assertOk();
        $this->actingAs($this->manager)->postJson('/caja/movimientos', ['type' => 'gasto', 'amount' => 80, 'reason' => 'Luz',
            'expense_category_id' => $this->cat('Servicios')])->assertOk();

        $this->assertSame($this->cat('Otros'), CashMovement::where('reason', 'Bolsas')->value('expense_category_id'));
        $this->assertSame($this->cat('Servicios'), CashMovement::where('reason', 'Luz')->value('expense_category_id'));
        $this->actingAs($this->manager)->get('/caja')->assertSee('Categoría (opcional)');
    }

    public function test_el_manager_registra_un_gasto_general_desde_el_panel(): void
    {
        Livewire::actingAs($this->manager)
            ->test(ManageGeneralMovements::class)
            ->callAction('create', data: ['type' => 'gasto', 'amount' => 1500, 'expense_category_id' => $this->cat('Nómina'),
                'occurred_on' => now()->toDateString(), 'description' => 'Sueldo de Luis'])
            ->assertHasNoActionErrors();

        $gasto = GeneralMovement::firstOrFail();
        $this->assertSame($this->manager->id, $gasto->user_id);
        $this->assertSame('Sueldo de Luis', $gasto->description);

        $cajero = User::create(['name' => 'Cajero', 'email' => 'c@test.local', 'password' => 'x', 'pin' => '4321',
            'role_id' => Role::firstOrCreate(['name' => 'cajero'])->id, 'is_active' => true]);
        $this->actingAs($cajero);
        $this->assertFalse(GeneralMovementResource::canAccess());
    }

    public function test_dashboard_pdf_y_excel_traen_la_ganancia(): void
    {
        $this->escenario();

        Livewire::actingAs($this->manager)->test(FinanceStatsOverview::class, ['pageFilters' => ['range' => 'today']])
            ->assertSee('Ganancia')->assertSee('$650.00');

        $service = ReportMetricsService::fromFilters(['range' => 'today']);
        $pdf = view('reportes.pdf', ['service' => $service, 'from' => $service->from, 'until' => $service->until])->render();
        $this->assertStringContainsString('Ingresos, gastos y ganancia', $pdf);
        $this->assertStringContainsString('$650.00', $pdf);
        $this->assertStringContainsString('Por turno', $pdf);
        $this->assertStringContainsString('Generales (fuera de caja)', $pdf);

        $rows = (new ReportFinanceSheet($service))->array();
        $this->assertContains(['Resumen', 'Ganancia (ventas − gastos)', '', '', 650.0], $rows);
    }
}
