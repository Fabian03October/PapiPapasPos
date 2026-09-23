<?php

namespace App\Filament\Resources\SaleIncidents;

use App\Filament\Resources\SaleIncidents\Pages\ManageSaleIncidents;
use App\Models\SaleIncident;
use App\Services\SaleIncidentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleIncidentResource extends Resource
{
    protected static ?string $model = SaleIncident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Incidencias';
    protected static ?string $modelLabel = 'incidencia';
    protected static ?string $pluralModelLabel = 'incidencias';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sale.folio')->label('Folio'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'cancelacion' ? 'Cancelación' : 'Reposición')
                    ->color(fn (string $state) => $state === 'cancelacion' ? 'danger' : 'warning'),
                TextColumn::make('reason')->label('Razón')->limit(40),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pendiente' => 'Pendiente',
                        'aprobada' => 'Aprobada',
                        'rechazada' => 'Rechazada',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pendiente' => 'warning',
                        'aprobada' => 'success',
                        'rechazada' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('requestedBy.name')->label('Solicitó'),
                TextColumn::make('authorizedBy.name')->label('Autorizó')->placeholder('—'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/M/Y H:i'),
            ])
            ->recordActions([
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (SaleIncident $record) => $record->status === 'pendiente')
                    ->requiresConfirmation()
                    ->action(function (SaleIncident $record) {
                        app(SaleIncidentService::class)->approve($record, auth()->user());

                        Notification::make()->title('Incidencia aprobada')->success()->send();
                    }),
                Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (SaleIncident $record) => $record->status === 'pendiente')
                    ->requiresConfirmation()
                    ->action(function (SaleIncident $record) {
                        app(SaleIncidentService::class)->reject($record, auth()->user());

                        Notification::make()->title('Incidencia rechazada')->danger()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSaleIncidents::route('/'),
        ];
    }
}