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

    /** Entrada de insumo (compra): sube la existencia y, si se dio, actualiza el costo. */
    public function registerEntry(float $qty, ?float $unitCost, ?int $userId): void
    {
        InventoryMovement::create([
            'ingredient_id' => $this->id,
            'type' => 'compra',
            'qty' => $qty,
            'unit_cost' => $unitCost ?? $this->cost_per_unit, // sin costo nuevo, el que ya tenía
            'user_id' => $userId,
        ]);

        $this->increment('stock_qty', $qty);

        if ($unitCost !== null) {
            $this->update(['cost_per_unit' => $unitCost]);
        }
    }
}
