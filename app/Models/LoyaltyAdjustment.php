<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un ajuste a mano de los sellos/nivel de un cliente (bitácora). */
class LoyaltyAdjustment extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'before', 'after', 'reward_given', 'reason'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
