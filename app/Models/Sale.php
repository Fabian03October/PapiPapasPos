<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    public const PAYMENT_METHODS = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
    ];

    public const PAYMENT_ICONS = [
        'efectivo' => '💵',
        'tarjeta' => '💳',
        'transferencia' => '🏦',
    ];

    public static function paymentLabel(?string $method): string
    {
        return self::PAYMENT_METHODS[$method] ?? ucfirst((string) $method);
    }

    protected $fillable = [
        'folio',
        'user_id',
        'cash_session_id',
        'customer_id',
        'status',
        'revert_type',
        'reverted_at',
        'reverted_by',
        'revert_reason',
        'corrects_sale_id',
        'replaced_by_sale_id',
        'subtotal',
        'discount',
        'manual_discount',
        'discount_breakdown',
        'total',
        'payment_method',
        'amount_received',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** La venta que esta corrige (cuando se usó "Corregir"). */
    public function correctsSale()
    {
        return $this->belongsTo(Sale::class, 'corrects_sale_id');
    }

    /** La venta nueva que reemplazó a esta al corregirla. */
    public function replacedBy()
    {
        return $this->belongsTo(Sale::class, 'replaced_by_sale_id');
    }

    public function revertedByUser()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function isReverted(): bool
    {
        return $this->revert_type !== null;
    }

    public function incidents()
    {
        return $this->hasMany(SaleIncident::class);
    }

    /**
     * Con cuánto pagó en efectivo, para "Recibido / Cambio" en el ticket.
     * $override es lo que mandó la pantalla al cobrar (impresión al
     * momento); si no, lo guardado en la venta. Nunca menos que el total.
     * Ventas anteriores a que se guardara (null) no lo muestran: no se sabe.
     */
    public function cashReceived(?float $override = null): ?float
    {
        $paid = $override ?? ($this->amount_received !== null ? (float) $this->amount_received : null);

        return $paid === null ? null : max($paid, (float) $this->total);
    }

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'counted_visit' => 'boolean',
            'reverted_at' => 'datetime',
            'discount_breakdown' => 'array',
        ];
    }
}
