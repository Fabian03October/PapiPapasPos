<?php

namespace Tests\Feature;

use App\Models\Customer;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Paquete 2: servidor de producción, idioma del panel y detalles de "Mi tarjeta". */
class Package2Test extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_sale_en_espanol_aunque_el_idioma_sea_es_mx(): void
    {
        app()->setLocale('es_MX');

        // Filament trae "es", no "es_MX": antes caía al inglés ("Search").
        $this->assertSame('Búsqueda', __('filament-tables::table.fields.search.label'));
        // Los mensajes base de Laravel siguen teniendo respaldo (en).
        $this->assertStringNotContainsString('validation.', __('validation.required', ['attribute' => 'nombre']));
    }

    public function test_subir_archivos_dice_su_texto_en_espanol(): void
    {
        $upload = FileUpload::make('foto');

        $this->assertStringContainsString('Arrastra tu archivo aquí', $upload->getPlaceholder());
    }

    public function test_el_cumpleanos_se_busca_por_su_columna_con_indice(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5570000001', 'birthdate' => '1995-03-14']);
        Customer::create(['name' => 'Sin cumple', 'phone' => '5570000002']);

        $this->assertSame('03-14', $ana->fresh()->birth_md);
        $this->assertNull(Customer::where('phone', '5570000002')->value('birth_md'));
    }

    public function test_cliente_sin_sellos_ve_una_invitacion_a_su_primera_visita(): void
    {
        $nuevo = Customer::create(['name' => 'Nuevo', 'phone' => '5570000003']);
        $conSellos = Customer::create(['name' => 'Con sellos', 'phone' => '5570000004', 'current_visits' => 2]);

        $this->get("/mi-tarjeta/{$nuevo->qr_code}")->assertSee('Tu primera visita te da tu primer sello');
        $this->get("/mi-tarjeta/{$conSellos->qr_code}")->assertDontSee('Tu primera visita')->assertSee('Próximo premio');
    }

    public function test_recien_registrado_ve_como_guardarla_arriba_y_una_sola_vez(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'phone' => '5570000005']);

        $nuevo = $this->get("/mi-tarjeta/{$ana->qr_code}?nuevo=1")->getContent();
        $normal = $this->get("/mi-tarjeta/{$ana->qr_code}")->getContent();

        $this->assertSame(1, substr_count($nuevo, 'id="guardar"'));
        $this->assertSame(1, substr_count($normal, 'id="guardar"'));
        // Nuevo: antes del QR. Normal: después.
        $this->assertLessThan(strpos($nuevo, 'id="btn-qr-grande"'), strpos($nuevo, 'id="guardar"'));
        $this->assertGreaterThan(strpos($normal, 'id="btn-qr-grande"'), strpos($normal, 'id="guardar"'));
    }

    public function test_la_pagina_arranca_con_el_servidor_de_produccion(): void
    {
        $procfile = file_get_contents(base_path('Procfile'));
        $caddy = file_get_contents(base_path('Caddyfile'));

        $this->assertStringNotContainsString('artisan serve', $procfile);
        $this->assertStringContainsString('docker-php-entrypoint --config /app/Caddyfile', $procfile);
        $this->assertStringContainsString('root * /app/public', $caddy);
        $this->assertStringContainsString('hide .env*', $caddy);
        $this->assertStringContainsString('level WARN', $caddy);
    }
}
