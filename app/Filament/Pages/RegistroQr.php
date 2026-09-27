<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class RegistroQr extends Page
{
    protected string $view = 'filament.pages.registro-qr';

    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Código QR de registro';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Código QR de registro';

    public function getRegistrationUrl(): string
    {
        return route('registro.form');
    }

    public function getQrBase64(): string
    {
        return base64_encode(
            QrCode::format('svg')->size(360)->margin(1)->generate($this->getRegistrationUrl())
        );
    }
}
