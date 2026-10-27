<?php

namespace App\Filament\Pages;

use App\Models\LoyaltyAdjustment;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Bitácora de los ajustes a mano de sellos/nivel ("Sellos y nivel" en Clientes). */
class LoyaltyAdjustmentsLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.loyalty-adjustments-log';

    protected static ?string $slug = 'bitacora-de-sellos';

    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Bitácora de sellos';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Bitácora de sellos';

    public static function canAccess(): bool
    {
        return auth()->user()?->esManager() ?? false;
    }

    public function table(Table $table): Table
    {
        $fmt = fn (?array $s) => $s ? "Nivel {$s['level']} · {$s['visits']} sello(s) · {$s['cards']} tarjeta(s) llena(s)" : '—';

        return $table
            ->query(LoyaltyAdjustment::query()->with(['customer:id,name', 'user:id,name'])->latest())
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/M/Y H:i')->sortable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable(),
                TextColumn::make('before')->label('Antes')->formatStateUsing(fn ($state, LoyaltyAdjustment $record) => $fmt($record->before)),
                TextColumn::make('after')->label('Después')->formatStateUsing(fn ($state, LoyaltyAdjustment $record) => $fmt($record->after)),
                TextColumn::make('reward_given')->label('Premio dado')->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'discount' => 'Descuento',
                        'gift' => 'Regalo',
                        default => '—',
                    }),
                TextColumn::make('reason')->label('Motivo')->wrap(),
                TextColumn::make('user.name')->label('Hizo el cambio'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
