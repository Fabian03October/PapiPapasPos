<?php

namespace App\Filament\Resources\ModifierRecipeItems\Pages;

use App\Filament\Resources\ModifierRecipeItems\ModifierRecipeItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModifierRecipeItem extends CreateRecord
{
    protected static string $resource = ModifierRecipeItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}