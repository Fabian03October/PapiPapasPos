<?php

namespace App\Models;

use App\Services\ManualDiscountService;
use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }
}