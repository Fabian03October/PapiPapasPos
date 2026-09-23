<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'qr_code',
        'current_level',
        'current_visits',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->qr_code)) {
                $customer->qr_code = (string) Str::uuid();
            }
        });
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function loyaltyRedemptions()
    {
        return $this->hasMany(CustomerLoyaltyRedemption::class);
    }

    public function currentLoyaltyLevel(): ?LoyaltyLevel
    {
        return LoyaltyLevel::forLevelNumber($this->current_level);
    }
}