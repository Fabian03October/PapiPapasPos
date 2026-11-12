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
        'print_delay_seconds',
        'inventory_count_every_days',
        'inventory_count_weekdays',
        'whatsapp_card_message',
    ];

    // Valor de la base, también en la fila recién creada por current().
    protected $attributes = ['inventory_count_every_days' => 1];

    protected function casts(): array
    {
        return [
            'show_iva' => 'boolean',
            'personalize_thank_you' => 'boolean',
            'inventory_count_every_days' => 'integer',
            'inventory_count_weekdays' => 'array',
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
    /** "Cada cuánto" = ciertos días de la semana (los de inventory_count_weekdays). */
    public const INVENTORY_COUNT_ON_WEEKDAYS = 100;

    public const WEEKDAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];

    public const INVENTORY_COUNT_EVERY = [
        1 => 'En cada cierre de caja',
        self::INVENTORY_COUNT_ON_WEEKDAYS => 'Ciertos días de la semana',
        2 => 'Cada 2 días',
        3 => 'Cada 3 días',
        7 => 'Cada semana',
        15 => 'Cada 15 días',
        30 => 'Cada mes',
        0 => 'Nunca (solo cuando el manager quiera)',
    ];

    /** ¿Este cierre de caja pide contar el inventario? (lo decide el manager en Configuración). */
    public function inventoryCountDue(): bool
    {
        $days = (int) $this->inventory_count_every_days;

        return match (true) {
            $days === 0 => false,
            $days === 1 => true,
            $days === self::INVENTORY_COUNT_ON_WEEKDAYS => in_array((int) now()->format('w'), array_map('intval', $this->inventory_count_weekdays ?? []), true),
            // Cualquier conteo cuenta, también los de "Contar inventario".
            default => ! InventoryCount::where('created_at', '>=', now()->subDays($days))->exists(),
        };
    }

    public static function current(): self
    {
        return static::first() ?? static::create([
            'name' => "Papi's Papas",
            'thank_you_message' => '¡Gracias por tu compra!',
        ]);
    }
}
