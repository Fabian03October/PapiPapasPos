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
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->qr_code)) {
                $customer->qr_code = (string) Str::uuid();
            }
        });
    }

    public function loyaltyCards()
    {
        return $this->hasMany(CustomerLoyaltyCard::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}