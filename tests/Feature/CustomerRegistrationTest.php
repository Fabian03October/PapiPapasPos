<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_exitoso_crea_cliente_con_qr_code(): void
    {
        $response = $this->post('/registro', [
            'name' => 'Juan Pérez',
            'phone' => '5512345678',
        ]);

        $customer = Customer::where('phone', '5512345678')->first();

        $this->assertNotNull($customer);
        $this->assertNotEmpty($customer->qr_code);
        $this->assertSame('Juan Pérez', $customer->name);
        $response->assertRedirect(route('registro.confirmacion', $customer->qr_code));
    }

    public function test_telefono_repetido_no_duplica_cliente(): void
    {
        $existing = Customer::create(['name' => 'Ana López', 'phone' => '5599998888']);

        $response = $this->post('/registro', [
            'name' => 'Otro Nombre',
            'phone' => '55 9999 8888',
        ]);

        $this->assertSame(1, Customer::where('phone', '5599998888')->count());
        $response->assertRedirect(route('registro.confirmacion', $existing->qr_code));
    }

    public function test_nombre_requerido(): void
    {
        $this->post('/registro', ['phone' => '5512345678'])
            ->assertSessionHasErrors('name');
    }

    public function test_telefono_con_menos_de_diez_digitos_es_rechazado(): void
    {
        $this->post('/registro', ['name' => 'Juan', 'phone' => '12345'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, Customer::count());
    }

    public function test_confirmacion_muestra_el_qr_del_cliente(): void
    {
        $customer = Customer::create(['name' => 'María', 'phone' => '5511112222']);

        $this->get(route('registro.confirmacion', $customer->qr_code))
            ->assertOk()
            ->assertSee('María');
    }

    public function test_rate_limiting_bloquea_intentos_excesivos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/registro', ['name' => 'Test', 'phone' => '55000000'.$i]);
        }

        $this->post('/registro', ['name' => 'Test', 'phone' => '5500000009'])
            ->assertStatus(429);
    }
}
