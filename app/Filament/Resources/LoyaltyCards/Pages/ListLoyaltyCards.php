<?php

namespace App\Filament\Resources\LoyaltyCards\Pages;

use App\Filament\Resources\LoyaltyCards\LoyaltyCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyCards extends ListRecords
{
    protected static string $resource = LoyaltyCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
