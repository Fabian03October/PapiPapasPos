<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletMessage extends Model
{
    protected $fillable = [
        'title',
        'body',
        'audience',
        'customer_id',
        'notify',
        'starts_at',
        'ends_at',
        'sent_by_user_id',
        'status',
        'error',
    ];

    protected $casts = [
        'notify' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public static function dailyBroadcastCount(): int
    {
        return static::query()
            ->where('audience', 'all')
            ->where('notify', true)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public static function dailyBroadcastLimitReached(): bool
    {
        return static::dailyBroadcastCount() >= (int) config('services.google_wallet.daily_broadcast_limit', 1);
    }
}
