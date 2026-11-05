<?php

namespace App\Filament\Resources\ModifierRecipeItems\Pages;

use App\Filament\Resources\ModifierRecipeItems\ModifierRecipeItemResource;
use App\Filament\Support\IngredientRows;
use App\Models\Modifier;
use App\Models\ModifierRecipeItem;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Resources\Pages\ListRecords;

class ListModifierRecipeItems extends ListRecords
{
    protected static string $resource = ModifierRecipeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('addMany')
                ->label('Agregar varios insumos')
                ->icon(Heroicon::OutlinedQueueList)
                ->authorize(fn () => auth()->user()?->esManager() ?? false)
                ->schema([
                    Select::make('modifier_id')
                        ->label('Modificador')
                        ->options(Modifier::with('group')->get()->mapWithKeys(
                            fn ($m) => [$m->id => $m->group->name.' → '.$m->name]
                        ))
                        ->required()
                        ->searchable(),
                    ...IngredientRows::schema(),
                ])
                ->action(function (array $data): void {
                    foreach ($data['items'] as $row) {
                        ModifierRecipeItem::updateOrCreate(
                            ['modifier_id' => $data['modifier_id'], 'ingredient_id' => $row['ingredient_id']],
                            ['qty' => $row['qty']],
                        );
                    }
                    Notification::make()->title(count($data['items']).' insumo(s) en la receta')->success()->send();
                }),
        ];
    }
}
