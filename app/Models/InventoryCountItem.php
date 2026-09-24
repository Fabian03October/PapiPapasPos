<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCountItem extends Model
{
    protected $fillable = [
        'inventory_count_id',
        'ingredient_id',
        'expected_qty',
        'counted_qty',
        'difference',
    ];

    public function inventoryCount()
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
