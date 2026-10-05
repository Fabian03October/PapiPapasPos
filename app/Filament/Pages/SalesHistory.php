<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SalesHistoryStats;
use App\Models\Sale;
use App\Services\ReportMetricsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class SalesHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.sales-history';

    protected static string|\UnitEnum|null $navigationGroup = 'Ventas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Historial de ventas';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Historial de ventas';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['range' => 'today']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('range')
                    ->label('Periodo')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable())
                    ->options([
                        'today' => 'Hoy',
                        'yesterday' => 'Ayer',
                        'last_7_days' => 'Últimos 7 días',
                        'this_month' => 'Este mes',
                        'custom' => 'Personalizado',
                    ])
                    ->default('today'),
                DatePicker::make('from')
                    ->label('Desde')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable())
                    ->visible(fn (Get $get) => $get('range') === 'custom'),
                DatePicker::make('until')
                    ->label('Hasta')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable())
                    ->visible(fn (Get $get) => $get('range') === 'custom'),
            ])
            ->statePath('data');
    }

    protected function service(): ReportMetricsService
    {
        return ReportMetricsService::fromFilters($this->data ?: ['range' => 'today']);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SalesHistoryStats::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'range' => $this->data['range'] ?? 'today',
            'from' => $this->data['from'] ?? null,
            'until' => $this->data['until'] ?? null,
        ];
    }

    public function table(Table $table): Table
    {
        $service = $this->service();

        return $table
            ->query(
                Sale::query()
                    ->with(['user', 'customer', 'items', 'incidents'])
                    ->whereBetween('created_at', [$service->from, $service->until])
            )
            ->columns([
                TextColumn::make('folio')->label('Folio')->searchable(),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/M/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Cajero')->searchable(),
                TextColumn::make('customer.name')->label('Cliente')->placeholder('—')->searchable(),
                TextColumn::make('payment_method')
                    ->label('Pago')
                    ->formatStateUsing(fn (string $state) => $state === 'efectivo' ? 'Efectivo' : 'Tarjeta'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(function (Sale $record) {
                        if ($record->status === 'cancelada') {
                            return 'Cancelada';
                        }

                        if ($record->items->whereNotNull('cancelled_at')->isNotEmpty()) {
                            return 'Cancelación parcial';
                        }

                        if ($record->incidents->where('type', 'reposicion')->where('status', 'aprobada')->isNotEmpty()) {
                            return 'Con reposición';
                        }

                        return 'Pagada';
                    })
                    ->color(fn (Sale $record) => match (true) {
                        $record->status === 'cancelada' => 'danger',
                        $record->items->whereNotNull('cancelled_at')->isNotEmpty() => 'warning',
                        $record->incidents->where('type', 'reposicion')->where('status', 'aprobada')->isNotEmpty() => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('total')->label('Total')->money('MXN')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('ver')
                    ->label('Ver productos')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Sale $record) => 'Venta #' . $record->folio)
                    ->schema(fn (Sale $record) => [
                        RepeatableEntry::make('items')
                            ->label('')
                            ->state(
                                $record->loadMissing(['items.product', 'items.variant', 'items.modifiers.modifier'])
                                    ->items
                                    ->map(function ($item) {
                                        $description = $item->qty . 'x ' . ($item->product->name ?? 'Producto eliminado');

                                        if ($item->variant) {
                                            $description .= ' (' . $item->variant->name . ')';
                                        }

                                        foreach ($item->modifiers as $saleItemModifier) {
                                            if ($saleItemModifier->modifier) {
                                                $description .= "\n+ " . $saleItemModifier->modifier->name;
                                            }
                                        }

                                        if ($item->cancelled_at) {
                                            $description .= "\n(cancelado)";
                                        }

                                        return ['description' => $description, 'total' => (float) $item->line_total];
                                    })
                                    ->toArray()
                            )
                            ->schema([
                                TextEntry::make('description')->label('')->columnSpan(2),
                                TextEntry::make('total')->label('')->money('MXN'),
                            ])
                            ->columns(3),
                        TextEntry::make('sale_total')
                            ->label('Total')
                            ->state($record->total)
                            ->money('MXN')
                            ->weight('bold'),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ]);
    }
}
