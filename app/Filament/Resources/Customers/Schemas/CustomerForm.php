<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel(),
                TextInput::make('email')
                    ->label('Correo')
                    ->email(),
                TextInput::make('qr_code')
                    ->label('Código QR (generado automáticamente)')
                    ->disabled()
                    ->dehydrated(false)
                    ->copyable()
                    ->visible(fn (string $operation) => $operation === 'edit')
                    ->helperText('Este código se genera solo al crear el cliente y no se puede editar.'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->helperText('Desactívalo en vez de borrarlo para conservar su historial de compras y lealtad.')
                    ->default(true),
            ]);
    }
}