<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierRecipeItem extends Model
{
    protected $fillable = [
        'modifier_id',
        'ingredient_id',
        'qty',
    ];

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}