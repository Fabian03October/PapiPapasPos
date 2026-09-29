<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'qr_code',
        'current_level',
        'current_visits',
        'is_active',
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

    public function loyaltyQrBase64(int $size = 280): string
    {
        return base64_encode(QrCode::format('svg')->size($size)->margin(1)->generate($this->qr_code));
    }
}