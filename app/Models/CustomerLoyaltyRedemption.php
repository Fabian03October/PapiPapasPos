<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLoyaltyRedemption extends Model
{
    protected $fillable = [
        'customer_id',
        'loyalty_level_id',
        'type',
        'status',
        'earned_at',
        'expires_at',
        'redeemed_at',
        'sale_id',
        'redeemed_sale_id',
        'sale_item_id',
        'discount_applied',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loyaltyLevel()
    {
        return $this->belongsTo(LoyaltyLevel::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function redeemedSale()
    {
        return $this->belongsTo(Sale::class, 'redeemed_sale_id');
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    /** "10% de descuento", "Papis chicas gratis"... (lo que dice la tarjeta y los avisos). */
    public function rewardLabel(): string
    {
        $level = $this->loyaltyLevel;

        return $this->type === 'discount'
            ? ($level?->discount_description ?: ($level?->discount_percent ?? '').'% de descuento')
            : ($level?->gift_description ?: 'Producto gratis');
    }
}
