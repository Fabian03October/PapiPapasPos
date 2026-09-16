<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyCardMilestone extends Model
{
    protected $fillable = [
        'loyalty_card_id',
        'visits_required',
        'reward_description',
        'sort_order',
        'reward_type',
        'reward_value',
        'reward_product_id',
    ];

    public function loyaltyCard()
    {
        return $this->belongsTo(LoyaltyCard::class);
    }

    public function rewardProduct()
    {
        return $this->belongsTo(Product::class, 'reward_product_id');
    }
}