<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un iPhone que guardó el pase y quiere recibir sus actualizaciones. */
class AppleWalletRegistration extends Model
{
    protected $fillable = ['apple_wallet_pass_id', 'device_library_identifier', 'push_token'];

    protected $hidden = ['push_token'];

    public function pass()
    {
        return $this->belongsTo(AppleWalletPass::class, 'apple_wallet_pass_id');
    }
}
