<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyLevel extends Model
{
    protected $fillable = [
        'level_number',
        'repeat_times',
        'discount_percent',
        'discount_description',
        'free_product_id',
        'gift_description',
        'is_active',
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

    /** Veces que se llena la tarjeta en este nivel antes de subir (mínimo 1). */
    public function timesToAdvance(): int
    {
        return max(1, (int) $this->repeat_times);
    }

    /** El primer nivel (al que regresan al terminar el último). */
    public static function firstLevel(): ?self
    {
        return static::orderBy('level_number')->first();
    }

    public static function forLevelNumber(?int $levelNumber): ?self
    {
        if ($levelNumber === null) {
            return null;
        }

        return static::where('level_number', $levelNumber)->first();
    }
}
