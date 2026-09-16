<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLoyaltyRedemption extends Model
{
    protected $fillable = [
        'customer_loyalty_card_id',
        'milestone_id',
        'redeemed_at',
        'sale_id',
        'sale_item_id',
        'discount_applied',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
        ];
    }

    public function customerLoyaltyCard()
    {
        return $this->belongsTo(CustomerLoyaltyCard::class);
    }

    public function milestone()
    {
        return $this->belongsTo(LoyaltyCardMilestone::class, 'milestone_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}