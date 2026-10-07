<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'image_path',
        'base_price',
        'prints_to_kitchen',
        'is_active',
    ];

    protected static function booted(): void
    {
        // Filament no borra la foto anterior al reemplazarla o quitarla, así
        // que sin esto el disco se va llenando de imágenes huérfanas.
        static::updated(function (Product $product) {
            $old = $product->getOriginal('image_path');

            if ($product->wasChanged('image_path') && $old) {
                Storage::disk('public')->delete($old);
            }
        });

        static::deleted(function (Product $product) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_group');
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_products')
            ->withPivot('qty_required');
    }
}