<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyLevel extends Model
{
    protected $fillable = [
        'level_number',
        'discount_percent',
        'discount_description',
        'free_product_id',
        'gift_description',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
        ];
    }

    public function freeProduct()
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }

    public function redemptions()
    {
        return $this->hasMany(CustomerLoyaltyRedemption::class);
    }

    public static function forLevelNumber(int $levelNumber): ?self
    {
        return static::where('level_number', $levelNumber)->first();
    }
}