<?php

namespace App\Filament\Resources\ModifierRecipeItems\Pages;

use App\Filament\Resources\ModifierRecipeItems\ModifierRecipeItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModifierRecipeItems extends ListRecords
{
    protected static string $resource = ModifierRecipeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
