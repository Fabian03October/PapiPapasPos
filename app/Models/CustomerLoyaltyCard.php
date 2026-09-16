<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLoyaltyCard extends Model
{
    protected $fillable = [
        'customer_id',
        'loyalty_card_id',
        'current_visits',
        'completed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerLoyaltyCard $card) {
            if (($card->status ?? 'active') === 'active') {
                static::where('customer_id', $card->customer_id)
                    ->where('status', 'active')
                    ->update(['status' => 'abandoned']);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loyaltyCard()
    {
        return $this->belongsTo(LoyaltyCard::class);
    }

    public function redemptions()
    {
        return $this->hasMany(CustomerLoyaltyRedemption::class);
    }
}