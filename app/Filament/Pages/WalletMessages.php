<?php

namespace App\Filament\Pages;

use App\Models\WalletMessage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

class WalletMessages extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.wallet-messages';

    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Mensajes Wallet';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Mensajes Wallet';

    public function table(Table $table): Table
    {
        return $table
            ->query(WalletMessage::query()->latest())
            ->columns([
                TextColumn::make('title')->label('Título')->searchable(),
                TextColumn::make('audience')
                    ->label('Destino')
                    ->formatStateUsing(fn (string $state) => $state === 'all' ? 'Todos' : 'Cliente específico'),
                TextColumn::make('customer.name')->label('Cliente')->placeholder('—'),
                IconColumn::make('notify')->label('Notificó')->boolean(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'sent' => 'Enviado',
                        'failed' => 'Falló',
                        default => 'En cola',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sentBy.name')->label('Enviado por'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/M/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
