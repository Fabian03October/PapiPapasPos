<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Support\IngredientRows;
use App\Models\Ingredient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                Action::make('addMany')
                    ->label('Agregar varios insumos')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->authorize(fn () => auth()->user()?->esManager() ?? false)
                    ->schema([
                        Select::make('variant_id')
                            ->label('Variante (opcional)')
                            ->options(fn () => $this->getOwnerRecord()->variants()->pluck('name', 'id'))
                            ->helperText('Déjalo vacío si aplica al producto base, sin importar la variante.')
                            ->nullable(),
                        ...IngredientRows::schema(),
                    ])
                    ->action(function (array $data): void {
                        foreach ($data['items'] as $row) {
                            $this->getOwnerRecord()->recipeItems()->updateOrCreate(
                                ['variant_id' => $data['variant_id'] ?? null, 'ingredient_id' => $row['ingredient_id']],
                                ['qty' => $row['qty']],
                            );
                        }
                        Notification::make()->title(count($data['items']).' insumo(s) en la receta')->success()->send();
                    }),
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