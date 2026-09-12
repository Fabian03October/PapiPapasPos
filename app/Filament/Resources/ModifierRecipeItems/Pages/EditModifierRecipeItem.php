<?php

namespace App\Filament\Resources\ModifierRecipeItems\Pages;

use App\Filament\Resources\ModifierRecipeItems\ModifierRecipeItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModifierRecipeItem extends EditRecord
{
    protected static string $resource = ModifierRecipeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}