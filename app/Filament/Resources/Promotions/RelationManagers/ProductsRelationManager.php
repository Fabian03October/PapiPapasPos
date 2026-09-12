<?php

namespace App\Filament\Resources\Promotions\RelationManagers;

use App\Models\Product;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Productos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Producto'),
                TextColumn::make('pivot.qty_required')
                    ->label('Cantidad requerida'),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label('Agregar producto')
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('is_active', true))
                    ->form(fn (AssociateAction $action) => [
                        $action->getRecordSelect(),
                        TextInput::make('qty_required')
                            ->label('Cantidad requerida')
                            ->numeric()
                            ->default(1)
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        TextInput::make('qty_required')
                            ->label('Cantidad requerida')
                            ->numeric()
                            ->required(),
                    ]),
                DissociateAction::make()
                    ->label('Quitar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                ]),
            ]);
    }
}