<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'unit',
        'stock_qty',
        'min_stock',
        'cost_per_unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function modifierRecipeItems()
    {
        return $this->hasMany(ModifierRecipeItem::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}