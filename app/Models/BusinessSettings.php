<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSettings extends Model
{
    protected $table = 'business_settings';

    protected $fillable = [
        'name',
        'address',
        'contact_info',
        'show_iva',
        'iva_rate',
        'thank_you_message',
        'personalize_thank_you',
    ];

    protected function casts(): array
    {
        return [
            'show_iva' => 'boolean',
            'personalize_thank_you' => 'boolean',
            'iva_rate' => 'decimal:2',
        ];
    }

    /**
     * Fila única de configuración. Ojo: buscarla por id=1 se rompe en
     * cuanto esa fila deja de tener exactamente ese id (ej. un rollback de
     * transacción en pruebas hace que MySQL nunca reutilice el 1) - cada
     * llamada crearía una fila nueva en vez de reusar la guardada. Por eso
     * se toma "la primera que exista", sin asumir cuál id tiene.
     */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'name' => "Papi's Papas",
            'thank_you_message' => '¡Gracias por tu compra!',
        ]);
    }
}
