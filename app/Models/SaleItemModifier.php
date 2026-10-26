<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItemModifier extends Model
{
    protected $fillable = [
        'sale_item_id',
        'modifier_id',
        'qty',
        'price_delta',
    ];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    /** "Queso" o "Queso ×2" (lo que dicen comanda, ticket y panel). */
    public function label(): string
    {
        $name = (string) $this->modifier?->name;
        $qty = max(1, (int) $this->qty);

        return $qty > 1 ? "{$name} ×{$qty}" : $name;
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }

    /**
     * Los extras (grupo "Extras") van destacados en la comanda: negrita y
     * subrayado, para que cocina no se los salte.
     */
    public function isExtra(): bool
    {
        return str_contains(mb_strtolower(trim((string) $this->modifier?->group?->name)), 'extra');
    }
}
