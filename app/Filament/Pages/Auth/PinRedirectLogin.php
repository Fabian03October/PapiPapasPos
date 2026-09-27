<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class PinRedirectLogin extends BaseLogin
{
    public function mount(): void
    {
        redirect()->route('login.pin');
        return;
    }
}
