<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagerAuthorizationCode extends Model
{
    protected $fillable = ['user_id', 'code', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }
}