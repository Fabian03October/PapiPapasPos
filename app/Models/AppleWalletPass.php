<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** El pase de Apple Wallet de un cliente (uno por cliente). */
class AppleWalletPass extends Model
{
    protected $fillable = ['customer_id', 'serial_number', 'authentication_token'];

    protected $hidden = ['authentication_token'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function registrations()
    {
        return $this->hasMany(AppleWalletRegistration::class);
    }
}
