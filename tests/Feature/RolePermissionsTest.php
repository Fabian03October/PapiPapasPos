<?php

namespace Tests\Feature;

use App\Filament\Resources\SaleIncidents\Pages\ManageSaleIncidents;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\ManagerAuthorizationCode;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Roles: el cajero solo vende; el gerente vende y entra al panel admin (todo
 * menos "Administración") y genera códigos; el manager entra a todo.
 * Cancelar o reponer: solo el cajero necesita código.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $pin = '1234'): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role . '@test.local', 'password' => 'password',
            'pin' => $pin, 'role_id' => Role::firstOrCreate(['name' => $role])->id, 'is_active' => true,
        ]);
    }

    private function saleItem(User $by): SaleItem
    {
        $session = CashSession::open() ?? CashSession::create(['user_id' => $by->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $category = Category::firstOrCreate(['name' => 'Papas'], ['sort_order' => 1, 'is_active' => true]);
        $product = Product::firstOrCreate(['name' => 'Salchipapas'], ['category_id' => $category->id, 'base_price' => 89, 'is_active' => true]);
        $sale = Sale::create([
            'folio' => 'T' . (Sale::count() + 1), 'user_id' => $by->id, 'cash_session_id' => $session->id, 'status' => 'pagada',
            'subtotal' => 89, 'discount' => 0, 'total' => 89, 'payment_method' => 'efectivo',
        ]);

        return SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'qty' => 1, 'unit_price' => 89, 'line_total' => 89]);
    }

    private function cancelar(User $as, SaleItem $item, string $mode, ?string $code = null)
    {
        return $this->actingAs($as)->postJson('/venta/incidencias', [
            'sale_item_ids' => [$item->id], 'type' => 'cancelacion', 'reason' => 'Se equivocó de producto',
            'mode' => $mode, 'code' => $code,
        ]);
    }

    public function test_el_cajero_no_entra_al_panel_admin_ni_ve_el_enlace(): void
    {
        $cajero = $this->user('cajero');
        CashSession::create(['user_id' => $cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);

        $this->actingAs($cajero)->get('/admin')->assertForbidden();
        $this->actingAs($cajero)->get('/admin/products')->assertForbidden();
        $this->actingAs($cajero)->get('/venta')->assertOk()->assertDontSee('href="/admin"', false);
    }

    public function test_el_gerente_entra_al_admin_menos_a_administracion(): void
    {
        $gerente = $this->user('gerente');
        CashSession::create(['user_id' => $gerente->id, 'opening_amount' => 0, 'opened_at' => now()]);

        $this->actingAs($gerente)->get('/admin')->assertOk()->assertDontSee('Usuarios')->assertDontSee('Datos del negocio');
        $this->actingAs($gerente)->get('/admin/products')->assertOk();
        $this->actingAs($gerente)->get('/admin/users')->assertForbidden();
        $this->actingAs($gerente)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($gerente)->get('/admin/business-settings-page')->assertForbidden();

        // Y también vende, con el enlace al admin en su menú.
        $this->actingAs($gerente)->get('/venta')->assertOk()->assertSee('href="/admin"', false);
    }

    public function test_el_manager_entra_a_todo(): void
    {
        $manager = $this->user('manager');

        $this->actingAs($manager)->get('/admin')->assertOk();
        $this->actingAs($manager)->get('/admin/users')->assertOk();
        $this->actingAs($manager)->get('/admin/business-settings-page')->assertOk();
    }

    public function test_el_gerente_puede_generar_codigos_para_los_cajeros(): void
    {
        $gerente = $this->user('gerente');

        Livewire::actingAs($gerente)
            ->test(ManageSaleIncidents::class)
            ->callAction('generar_codigo')
            ->assertHasNoActionErrors();

        $this->assertSame(1, ManagerAuthorizationCode::where('user_id', $gerente->id)->count());
    }

    public function test_manager_y_gerente_cancelan_sin_codigo_y_queda_quien_autorizo(): void
    {
        foreach (['manager' => '1111', 'gerente' => '2222'] as $role => $pin) {
            $user = $this->user($role, $pin);
            $item = $this->saleItem($user);

            $this->cancelar($user, $item, 'directo')->assertOk()->assertJson(['success' => true]);

            $incident = $item->sale->incidents()->first();
            $this->assertSame('aprobada', $incident->status, $role);
            $this->assertSame('pin', $incident->authorization_method, $role);
            $this->assertSame($user->id, $incident->authorized_by, $role);
            $this->assertNotNull($item->fresh()->cancelled_at, $role);
        }
    }

    public function test_el_cajero_si_necesita_codigo(): void
    {
        $cajero = $this->user('cajero');
        $item = $this->saleItem($cajero);

        // Sin código (intentando el modo directo) no puede.
        $this->cancelar($cajero, $item, 'directo')->assertForbidden();
        $this->assertNull($item->fresh()->cancelled_at);

        // Con un código válido del gerente, sí.
        $gerente = $this->user('gerente', '5678');
        ManagerAuthorizationCode::create(['user_id' => $gerente->id, 'code' => bcrypt('123456'), 'expires_at' => now()->addMinutes(10)]);

        $this->cancelar($cajero, $item, 'codigo', '123456')->assertOk();
        $incident = $item->sale->incidents()->first();
        $this->assertSame('codigo_temporal', $incident->authorization_method);
        $this->assertSame($gerente->id, $incident->authorized_by);
    }
}
