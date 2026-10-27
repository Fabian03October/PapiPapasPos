<?php

namespace App\Filament\Resources\DayPasses;

use App\Filament\Resources\DayPasses\Pages\ManageDayPasses;
use App\Models\DayPass;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pase del día: para gente que NO está en las tarjetas. El manager lo genera,
 * lo apunta en un papel y el cajero lo escribe en la venta (botón "Pase").
 * Un solo uso; no se liga a un cliente ni se junta con premios de la tarjeta.
 */
class DayPassResource extends Resource
{
    protected static ?string $model = DayPass::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Pases del día';

    protected static ?string $modelLabel = 'pase del día';

    protected static ?string $pluralModelLabel = 'pases del día';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->esManager() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('benefit_type')->label('Beneficio')
                    ->options(DayPass::BENEFITS)->default('product')->required()->live(),
                Select::make('product_id')->label('Producto gratis')
                    ->options(fn () => Product::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Get $get) => $get('benefit_type') === 'product')
                    ->required(fn (Get $get) => $get('benefit_type') === 'product'),
                TextInput::make('value')
                    ->label(fn (Get $get) => $get('benefit_type') === 'percent' ? 'Porcentaje' : 'Monto')
                    ->numeric()->minValue(0.01)
                    ->maxValue(fn (Get $get) => $get('benefit_type') === 'percent' ? 100 : null)
                    ->prefix(fn (Get $get) => $get('benefit_type') === 'amount' ? '$' : null)
                    ->suffix(fn (Get $get) => $get('benefit_type') === 'percent' ? '%' : null)
                    ->visible(fn (Get $get) => in_array($get('benefit_type'), ['percent', 'amount'], true))
                    ->required(fn (Get $get) => in_array($get('benefit_type'), ['percent', 'amount'], true)),
                DatePicker::make('expires_on')->label('Vale hasta')->helperText('Se puede usar hasta el final de ese día.')
                    ->default(now())->minDate(now()->startOfDay())->required(),
                TextInput::make('note')->label('Para quién')->placeholder('Opcional. Ej. Proveedor de papas')->maxLength(255),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product:id,name', 'usedSale:id,folio', 'creator:id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Código')
                    ->formatStateUsing(fn (DayPass $record) => $record->displayCode())
                    ->fontFamily('mono')->weight('bold')->size('lg')
                    ->copyable()->copyMessage('Código copiado')->copyableState(fn (DayPass $record) => $record->code)
                    ->searchable(),
                TextColumn::make('benefit')->label('Beneficio')->state(fn (DayPass $record) => $record->benefitLabel()),
                TextColumn::make('expires_on')->label('Vale hasta')->date('d/M/Y')->sortable(),
                TextColumn::make('note')->label('Para quién')->placeholder('—')->wrap(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->state(fn (DayPass $record) => $record->status())
                    ->color(fn (string $state) => match ($state) {
                        'Disponible' => 'success',
                        'Usado' => 'gray',
                        default => 'danger',
                    })
                    ->description(fn (DayPass $record) => $record->usedSale ? "Venta #{$record->usedSale->folio} · ".$record->used_at->format('d/M H:i') : null),
                TextColumn::make('creator.name')->label('Lo dio')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')
                    ->options(['disponible' => 'Disponibles', 'usado' => 'Usados', 'vencido' => 'Vencidos'])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'disponible' => $q->whereNull('used_at')->whereDate('expires_on', '>=', today()),
                        'usado' => $q->whereNotNull('used_at'),
                        'vencido' => $q->whereNull('used_at')->whereDate('expires_on', '<', today()),
                        default => $q,
                    }),
            ])
            // Uno usado ya es historia de una venta: no se borra.
            ->recordActions([DeleteAction::make()->visible(fn (DayPass $record) => $record->used_at === null)]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDayPasses::route('/'),
        ];
    }
}
