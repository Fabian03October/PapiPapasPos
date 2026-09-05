<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    protected $fillable = ['name', 'min_select', 'max_select', 'is_required', 'sort_order'];

    public function modifiers()
    {
        return $this->hasMany(Modifier::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifier_group');
    }
}