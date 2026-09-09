<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context) => $context === 'create')
                    ->helperText('Ya no se usa para iniciar sesión (todo es por PIN), pero Laravel requiere un valor aquí.'),
                TextInput::make('pin')
                    ->label('PIN provisional (4 dígitos)')
                    ->password()
                    ->revealable()
                    ->maxLength(4)
                    ->minLength(4)
                    ->rule('digits:4')
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context) => $context === 'create')
                    ->helperText('Al crear un usuario nuevo, se le pedirá cambiarlo en su primer login.'),
                Select::make('role_id')
                    ->label('Rol')
                    ->options(Role::pluck('name', 'id'))
                    ->required(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
                Toggle::make('must_change_pin')
                    ->label('Debe cambiar su PIN en el próximo login')
                    ->default(true)
                    ->helperText('Actívalo al crear un usuario nuevo con PIN provisional.'),
            ]);
    }
}