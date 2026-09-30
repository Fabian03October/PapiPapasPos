<?php

namespace Tests\Feature;

use App\Models\BusinessSettings;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido del dueño: el ticket no tenía dirección, contacto, desglose de
 * IVA (los precios ya lo incluyen, solo se muestra informativo) ni un
 * mensaje de agradecimiento personalizable - todo editable por el manager
 * desde el panel (BusinessSettingsPage), sin tocar código.
 */
class TicketBusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function cajero(): User
    {
        $role = Role::firstOrCreate(['name' => 'cajero']);

        return User::create([
            'name' => 'Cajero', 'email' => 'cajero+'.uniqid().'@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function sale(?Customer $customer = null): Sale
    {
        $user = $this->cajero();
        $cashSession = CashSession::create(['user_id' => $user->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Antojitos', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Salchipapas', 'base_price' => 116, 'is_active' => true]);

        $sale = Sale::create([
            'folio' => '0001',
            'user_id' => $user->id,
            'cash_session_id' => $cashSession->id,
            'customer_id' => $customer?->id,
            'status' => 'pagada',
            'subtotal' => 116,
            'discount' => 0,
            'total' => 116,
            'payment_method' => 'efectivo',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => 116,
            'line_total' => 116,
        ]);

        return $sale;
    }

    public function test_el_ticket_muestra_direccion_contacto_e_iva_por_default(): void
    {
        BusinessSettings::current()->update([
            'address' => 'Av. Principal 123',
            'contact_info' => '📞 555-123-4567',
        ]);

        $sale = $this->sale();

        $response = $this->actingAs($this->cajero())->get(route('venta.ticket', $sale));

        $response->assertOk();
        $response->assertSee('Av. Principal 123');
        $response->assertSee('555-123-4567', false);
        // $116 con IVA de 16% incluido: base = 100, IVA = 16.
        $response->assertSee('IVA incluido (16%): $16.00', false);
        $response->assertSee('¡Gracias por tu compra!');
    }

    public function test_se_puede_ocultar_el_desglose_de_iva(): void
    {
        BusinessSettings::current()->update(['show_iva' => false]);

        $sale = $this->sale();

        $response = $this->actingAs($this->cajero())->get(route('venta.ticket', $sale));

        $response->assertOk();
        $response->assertDontSee('IVA incluido', false);
    }

    public function test_mensaje_personalizado_con_el_nombre_del_cliente(): void
    {
        BusinessSettings::current()->update(['personalize_thank_you' => true]);
        $customer = Customer::create(['name' => 'Fabian', 'phone' => '5511112222']);

        $sale = $this->sale($customer);

        $response = $this->actingAs($this->cajero())->get(route('venta.ticket', $sale));

        $response->assertOk();
        $response->assertSee('¡Gracias, Fabian, por tu compra!');
    }

    public function test_sin_cliente_se_usa_el_mensaje_generico_aunque_este_activada_la_personalizacion(): void
    {
        BusinessSettings::current()->update([
            'personalize_thank_you' => true,
            'thank_you_message' => 'Vuelve pronto',
        ]);

        $sale = $this->sale();

        $response = $this->actingAs($this->cajero())->get(route('venta.ticket', $sale));

        $response->assertOk();
        $response->assertSee('Vuelve pronto');
    }
}
