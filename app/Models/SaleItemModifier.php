<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItemModifier extends Model
{
    protected $fillable = [
        'sale_item_id',
        'modifier_id',
        'price_delta',
    ];

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }
}