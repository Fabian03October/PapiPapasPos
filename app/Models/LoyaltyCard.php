<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyCard extends Model
{
    protected $fillable = [
        'name',
        'description',
        'reset_behavior',
        'valid_from',
        'valid_until',
        'is_active',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (LoyaltyCard $card) {
            if ($card->is_public) {
                static::where('id', '!=', $card->id)
                    ->where('is_public', true)
                    ->update(['is_public' => false]);
            }
        });
    }

    public function milestones()
    {
        return $this->hasMany(LoyaltyCardMilestone::class)->orderBy('sort_order');
    }

    public function customerCards()
    {
        return $this->hasMany(CustomerLoyaltyCard::class);
    }
}