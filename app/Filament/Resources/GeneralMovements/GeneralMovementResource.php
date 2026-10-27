<?php

namespace App\Filament\Resources\GeneralMovements;

use App\Filament\Resources\GeneralMovements\Pages\ManageGeneralMovements;
use App\Models\ExpenseCategory;
use App\Models\GeneralMovement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gastos e ingresos que NO salen de la caja (renta, luz, nómina, compra de
 * insumos...). Los del turno se registran en "Corte de caja". Los dos entran
 * al dashboard y al reporte (ganancia = ventas − gastos).
 */
class GeneralMovementResource extends Resource
{
    protected static ?string $model = GeneralMovement::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Gastos e ingresos';

    protected static ?string $modelLabel = 'gasto o ingreso';

    protected static ?string $pluralModelLabel = 'gastos e ingresos';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->esManager() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')->label('Tipo')
                    ->options(['gasto' => 'Gasto', 'ingreso' => 'Ingreso'])
                    ->default('gasto')->required(),
                TextInput::make('amount')->label('Monto')->numeric()->prefix('$')->minValue(0.01)->required(),
                Select::make('expense_category_id')->label('Categoría')
                    ->options(fn () => ExpenseCategory::options())
                    ->default(fn () => ExpenseCategory::otherId())
                    ->searchable()->required(),
                DatePicker::make('occurred_on')->label('Fecha')->default(now())->maxDate(now())->required(),
                TextInput::make('description')->label('Descripción')->placeholder('Ej. Renta de noviembre')
                    ->maxLength(255)->required()->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category:id,name', 'user:id,name']))
            ->defaultSort('occurred_on', 'desc')
            ->columns([
                TextColumn::make('occurred_on')->label('Fecha')->date('d/M/Y')->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'gasto' ? 'Gasto' : 'Ingreso')
                    ->color(fn (string $state) => $state === 'gasto' ? 'danger' : 'success'),
                TextColumn::make('category.name')->label('Categoría')->placeholder('—'),
                TextColumn::make('description')->label('Descripción')->searchable()->wrap(),
                TextColumn::make('amount')->label('Monto')->money('MXN')->sortable()
                    ->summarize(Sum::make()->money('MXN')->label('Total')),
                TextColumn::make('user.name')->label('Registró')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(['gasto' => 'Gastos', 'ingreso' => 'Ingresos']),
                SelectFilter::make('expense_category_id')->label('Categoría')->options(fn () => ExpenseCategory::options()),
                Filter::make('mes')->label('Este mes')->default()
                    ->query(fn (Builder $q) => $q->whereBetween('occurred_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGeneralMovements::route('/'),
        ];
    }
}
