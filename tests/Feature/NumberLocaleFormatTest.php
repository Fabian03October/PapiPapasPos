<?php

namespace Tests\Feature;

use Illuminate\Support\Number;
use Tests\TestCase;

/**
 * Regresión real: con APP_LOCALE=es (genérico, sin país), Filament formatea
 * los números/precios con el estilo de España (punto de miles, coma
 * decimal: "1.234,56") en vez del estilo de México que usa todo el sistema
 * ("1,234.56") - Filament::money()/numeric() caen en config('app.locale')
 * cuando no se les da un locale explícito. La corrección es usar la
 * variante regional "es_MX", que sí formatea correcto.
 */
class NumberLocaleFormatTest extends TestCase
{
    public function test_app_locale_es_mx_no_el_generico_es(): void
    {
        $this->assertSame('es_MX', config('app.locale'));
    }

    public function test_los_numeros_se_formatean_al_estilo_mexico_no_espana(): void
    {
        $locale = config('app.locale');

        $this->assertSame('1,234.56', Number::format(1234.56, locale: $locale));
        $this->assertSame('$1,234.56', Number::currency(1234.56, 'MXN', $locale));
    }
}
