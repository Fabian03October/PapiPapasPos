<?php

namespace App\Filament\Resources\LoyaltyCards\RelationManagers;

use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerCardsRelationManager extends RelationManager
{
    protected static string $relationship = 'customerCards';

    protected static ?string $title = 'Clientes asignados';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label('Cliente')
                    ->options(fn () => Customer::pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->helperText('Al asignar, si el cliente ya tenía otra tarjeta activa, esa queda marcada como abandonada automáticamente.'),
                TextInput::make('current_visits')
                    ->label('Visitas actuales')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('customer.name')
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Cliente'),
                TextColumn::make('current_visits')
                    ->label('Visitas'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'Activa',
                        'completed' => 'Completada',
                        'abandoned' => 'Abandonada',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'abandoned' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        TextInput::make('current_visits')
                            ->label('Visitas actuales')
                            ->numeric()
                            ->required(),
                    ]),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}