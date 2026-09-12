<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Ingredient;
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

class RecipeItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipeItems';

    protected static ?string $title = 'Receta';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('variant_id')
                    ->label('Variante (opcional)')
                    ->options(fn () => $this->getOwnerRecord()->variants()->pluck('name', 'id'))
                    ->helperText('Déjalo vacío si esta receta aplica al producto base, sin importar la variante.')
                    ->nullable(),
                Select::make('ingredient_id')
                    ->label('Insumo')
                    ->options(Ingredient::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('qty')
                    ->label('Cantidad')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->helperText('En la misma unidad del insumo (ej. gramos, ml, piezas).'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ingredient.name')
            ->columns([
                TextColumn::make('variant.name')
                    ->label('Variante')
                    ->placeholder('— (producto base)'),
                TextColumn::make('ingredient.name')
                    ->label('Insumo'),
                TextColumn::make('qty')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}