<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un celular que aceptó avisos de "Mi tarjeta" (Web Push). */
class PushSubscription extends Model
{
    protected $fillable = ['customer_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'user_agent', 'last_sent_at'];

    protected $hidden = ['endpoint', 'public_key', 'auth_token'];

    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
