<?php

namespace App\Filament\Resources\DayPasses\Pages;

use App\Filament\Resources\DayPasses\DayPassResource;
use App\Models\DayPass;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageDayPasses extends ManageRecords
{
    protected static string $resource = DayPassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Generar pase')
                ->modalHeading('Generar pase del día')
                ->mutateDataUsing(fn (array $data) => [
                    ...$data,
                    'created_by' => auth()->id(),
                    'product_id' => $data['benefit_type'] === 'product' ? ($data['product_id'] ?? null) : null,
                    'value' => $data['benefit_type'] === 'product' ? null : ($data['value'] ?? null),
                ])
                // El código, grande, para apuntarlo en el papel.
                ->successNotification(fn (DayPass $record) => Notification::make()
                    ->success()
                    ->title('Pase listo: '.$record->displayCode())
                    ->body($record->benefitLabel().' · vale hasta el '.$record->expires_on->locale('es')->translatedFormat('j \d\e F').'. Apúntalo en el papel.')
                    ->persistent()),
        ];
    }
}
