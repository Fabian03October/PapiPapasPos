<?php

namespace App\Models;

use App\Services\ManualDiscountService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'variant_id',
        'qty',
        'unit_price',
        'line_total',
        'notes',
        'manual_discount',
        'manual_discount_type',
        'manual_discount_value',
        'manual_discount_reason',
        'cancelled_at',
    ];

    /**
     * "Cortesía (motivo)" / "Desc. 10% (motivo)" / "Desc. $20 (motivo)" -
     * la misma etiqueta en ticket, historial del turno y admin.
     */
    public function manualDiscountLabel(): ?string
    {
        if ((float) $this->manual_discount <= 0) {
            return null;
        }

        return ManualDiscountService::label($this->manual_discount_type, $this->manual_discount_value, $this->manual_discount_reason);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function modifiers()
    {
        return $this->hasMany(SaleItemModifier::class);
    }

    /**
     * Los extras/aderezos en el orden canónico del negocio (el de la venta y
     * la comanda): primero los grupos obligatorios (la presentación: Domo,
     * Charola...), luego por el orden de cada grupo y, dentro del grupo, por
     * el orden de cada aderezo (Queso, Mayonesa, Kétchup...). Se configura en
     * el admin, arrastrando.
     */
    public function orderedModifiers(): Collection
    {
        $this->loadMissing('modifiers.modifier.group');

        return $this->modifiers
            ->filter(fn (SaleItemModifier $m) => $m->modifier !== null)
            ->sortBy(fn (SaleItemModifier $m) => [
                // Primero la presentación (grupo obligatorio); justo después
                // el Queso, antes que cualquier otro aderezo o salsa; luego
                // todo lo demás en el orden del catálogo.
                $m->modifier->group?->is_required ? 0 : 1,
                self::isQueso($m->modifier->name) ? 0 : 1,
                $m->modifier->group?->sort_order ?? PHP_INT_MAX,
                $m->modifier->sort_order ?? 0,
                $m->modifier->id,
            ])
            ->values();
    }

    /** "Queso" tal cual (sin importar mayúsculas/espacios); "Extra queso" no cuenta. */
    public static function isQueso(?string $name): bool
    {
        return mb_strtolower(trim((string) $name)) === 'queso';
    }

    /**
     * Lo que se cobra de las opciones de este producto (para el ticket del
     * cliente): solo las que cuestan, con su importe total (precio × veces
     * pedidas × cantidad del producto). Las gratis (presentación, aderezos,
     * salsas) van solo en la comanda.
     *
     * @return Collection<int, array{label: string, amount: float}>
     */
    public function chargedModifiers(): Collection
    {
        return $this->orderedModifiers()
            ->filter(fn (SaleItemModifier $m) => (float) $m->price_delta > 0)
            ->map(fn (SaleItemModifier $m) => [
                'label' => $m->label(),
                'amount' => round((float) $m->price_delta * max(1, (int) $m->qty) * $this->qty, 2),
            ])
            ->values();
    }

    /** El producto sin sus opciones de pago (para que el ticket sume bien). */
    public function baseAmount(): float
    {
        return round((float) $this->line_total - $this->chargedModifiers()->sum('amount'), 2);
    }

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }
}
