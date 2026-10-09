<?php

namespace Tests\Feature;

use App\Models\BusinessSettings;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemModifier;
use App\Models\User;
use App\Services\ThermalTicketBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ticket y comanda para la impresora Bluetooth de 58mm: el servidor los
 * manda ya acomodados a 32 columnas (impresora-bt.js solo los pasa a ESC/POS).
 */
class BluetoothTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = User::create([
            'name' => 'Gustavo Aquino', 'email' => 'cajero@test.local', 'password' => 'password',
            'pin' => '1234', 'role_id' => Role::firstOrCreate(['name' => 'cajero'])->id, 'is_active' => true,
        ]);
    }

    private function sale(): Sale
    {
        $session = CashSession::create(['user_id' => $this->cajero->id, 'opening_amount' => 0, 'opened_at' => now()]);
        $category = Category::create(['name' => 'Papas', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Salchipapas con queso y tocino extra grandes', 'base_price' => 89, 'is_active' => true]);
        $extras = ModifierGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);
        $queso = Modifier::create(['modifier_group_id' => $extras->id, 'name' => 'Queso', 'price_delta' => 15, 'is_active' => true]);

        $sale = Sale::create([
            'folio' => '0042', 'user_id' => $this->cajero->id, 'cash_session_id' => $session->id, 'status' => 'pagada',
            'subtotal' => 208, 'discount' => 20, 'manual_discount' => 20, 'total' => 188, 'payment_method' => 'efectivo',
            'discount_breakdown' => [['kind' => 'manual_venta', 'label' => 'Desc. $20.00 (Cliente frecuente)', 'product' => null, 'amount' => 20]],
        ]);
        $item = SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'qty' => 2, 'unit_price' => 104, 'line_total' => 208]);
        SaleItemModifier::create(['sale_item_id' => $item->id, 'modifier_id' => $queso->id, 'price_delta' => 15]);

        return $sale;
    }

    public function test_ningun_renglon_pasa_de_32_columnas_y_los_montos_van_a_la_derecha(): void
    {
        $lines = (new ThermalTicketBuilder)->forSale($this->sale(), 200);

        foreach ($lines as $line) {
            $max = $line['size'] === 'big' ? 16 : 32;
            $this->assertLessThanOrEqual($max, mb_strlen($line['text']), "Renglón demasiado largo: \"{$line['text']}\"");
        }

        $texts = array_column($lines, 'text');
        $total = collect($texts)->first(fn ($t) => str_starts_with($t, 'TOTAL'));
        $this->assertSame(32, mb_strlen($total));
        $this->assertStringEndsWith('$188.00', $total);

        // El nombre largo se parte y el precio queda en el último renglón.
        $this->assertContains('2x Salchipapas con queso', $texts);
        $this->assertContains('y tocino extra grandes   $208.00', $texts);
        $this->assertContains(' + Queso                  $30.00', $texts);

        $this->assertContains('Desc. $20.00 (Cliente', $texts);
        $this->assertContains('frecuente)               -$20.00', $texts);
        $this->assertTrue(collect($texts)->contains(fn ($t) => str_starts_with($t, 'Cambio') && str_ends_with($t, '$12.00')));
    }

    public function test_quita_caracteres_que_la_impresora_no_entiende_pero_deja_acentos(): void
    {
        BusinessSettings::current()->update(['name' => "Papi’s Papas 🍟", 'thank_you_message' => '¡Gracias por tu compra, vuelve pronto! ñ']);

        $texts = array_column((new ThermalTicketBuilder)->forSale($this->sale()), 'text');

        $this->assertSame("Papi's Papas", $texts[0]);
        $this->assertTrue(collect($texts)->contains(fn ($t) => str_contains($t, '¡Gracias')));
        $this->assertTrue(collect($texts)->contains(fn ($t) => str_contains($t, 'ñ')));
    }

    public function test_el_endpoint_regresa_ticket_y_comanda(): void
    {
        $sale = $this->sale();

        $response = $this->actingAs($this->cajero)
            ->getJson("/venta/{$sale->id}/impresion-bluetooth?recibido=200")
            ->assertOk()
            ->assertJsonStructure(['ticket' => [['text', 'align', 'bold', 'size']], 'comanda']);

        $comanda = array_column($response->json('comanda'), 'text');
        $this->assertSame('COMANDA', $comanda[0]);
        $this->assertSame('#0042', $comanda[1]);
        $this->assertContains('  + EXTRA Queso', $comanda);
    }

    public function test_la_pagina_de_prueba_carga(): void
    {
        $this->actingAs($this->cajero)->get('/prueba-impresora')
            ->assertOk()
            ->assertSee('Prueba de impresora Bluetooth');
    }
}
