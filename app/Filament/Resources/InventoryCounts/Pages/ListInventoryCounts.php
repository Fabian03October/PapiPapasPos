<?php

namespace App\Filament\Resources\InventoryCounts\Pages;

use App\Filament\Resources\InventoryCounts\InventoryCountResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInventoryCounts extends ListRecords
{
    protected static string $resource = InventoryCountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('contar')
                ->label('Contar inventario')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->visible(fn () => auth()->user()?->esManager() ?? false)
                ->url(route('inventario.contar')),
        ];
    }
}
