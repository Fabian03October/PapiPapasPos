<?php

namespace App\Filament\Resources\InventoryCounts\Pages;

use App\Filament\Resources\InventoryCounts\InventoryCountResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewInventoryCount extends ViewRecord
{
    protected static string $resource = InventoryCountResource::class;

    public function getTitle(): string
    {
        return 'Conteo del ' . $this->record->created_at->translatedFormat('j \d\e F, g:i a');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
