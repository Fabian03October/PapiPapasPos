<?php

namespace App\Filament\Pages;

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * Entrada de insumos (lo que llegó del proveedor). Es la pantalla del gerente
 * para el inventario: solo suma existencias, no edita ni borra insumos.
 */
class InventoryEntry extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.inventory-entry';

    protected static ?string $slug = 'entrada-de-insumos';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Entrada de insumos';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Entrada de insumos';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->puedeAutorizar() ?? false; // manager y gerente
    }

    public function mount(): void
    {
        $this->form->fill(['rows' => [['ingredient_id' => null, 'qty' => null, 'unit_cost' => null]]]);
    }

    public function form(Schema $schema): Schema
    {
        $ingredients = fn () => Ingredient::where('is_active', true)->orderBy('name')->get()->keyBy('id');

        return $schema
            ->components([
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->schema([
                        Select::make('ingredient_id')->label('Insumo')
                            ->options(fn () => $ingredients()->map(fn (Ingredient $i) => "{$i->name} ({$i->unit})"))
                            ->searchable()->required()->live()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->afterStateUpdated(fn ($state, $set) => $set('unit_cost', $ingredients()->get($state)?->cost_per_unit)),
                        TextInput::make('qty')->label('Cantidad que llegó')
                            ->numeric()->minValue(0.01)->required()
                            ->suffix(fn ($get) => $ingredients()->get($get('ingredient_id'))?->unit),
                        TextInput::make('unit_cost')->label('Costo por unidad')
                            ->numeric()->minValue(0)->prefix('$')
                            ->helperText('Opcional. Si cambió, se actualiza el costo del insumo.'),
                    ])
                    ->columns(3)
                    ->addActionLabel('Agregar otro insumo')
                    ->minItems(1)
                    ->reorderable(false),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $rows = $this->form->getState()['rows'];

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                Ingredient::where('is_active', true)->findOrFail($row['ingredient_id'])
                    ->registerEntry((float) $row['qty'], filled($row['unit_cost']) ? (float) $row['unit_cost'] : null, auth()->id());
            }
        });

        Notification::make()->title(count($rows).' entrada(s) registrada(s)')->success()->send();
        $this->mount();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Últimas entradas')
            ->query(InventoryMovement::query()->where('type', 'compra')->with(['ingredient:id,name,unit', 'user:id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/M/Y H:i')->sortable(),
                TextColumn::make('ingredient.name')->label('Insumo')->searchable(),
                TextColumn::make('qty')->label('Cantidad')
                    ->formatStateUsing(fn ($state, InventoryMovement $record) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.').' '.$record->ingredient?->unit),
                TextColumn::make('unit_cost')->label('Costo por unidad')->money('MXN')->placeholder('—'),
                TextColumn::make('user.name')->label('Quién')->placeholder('—'),
            ]);
    }
}
