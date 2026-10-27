<?php

namespace App\Filament\Resources\GeneralMovements\Pages;

use App\Filament\Resources\GeneralMovements\GeneralMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGeneralMovements extends ManageRecords
{
    protected static string $resource = GeneralMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar gasto o ingreso')
                ->mutateDataUsing(fn (array $data) => [...$data, 'user_id' => auth()->id()]),
        ];
    }
}
