<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'folio',
        'user_id',
        'cash_session_id',
        'customer_id',
        'status',
        'subtotal',
        'discount',
        'total',
        'payment_method',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

        public function incidents()
    {
        return $this->hasMany(SaleIncident::class);
    }
}