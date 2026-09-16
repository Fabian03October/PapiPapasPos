<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashSessionDenomination extends Model
{
    protected $fillable = [
        'cash_session_id',
        'denomination_value',
        'quantity',
    ];

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }
}