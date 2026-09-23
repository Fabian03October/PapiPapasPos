<?php

namespace App\Filament\Resources\LoyaltyLevels;

use App\Filament\Resources\LoyaltyLevels\Pages\CreateLoyaltyLevel;
use App\Filament\Resources\LoyaltyLevels\Pages\EditLoyaltyLevel;
use App\Filament\Resources\LoyaltyLevels\Pages\ListLoyaltyLevels;
use App\Filament\Resources\LoyaltyLevels\Schemas\LoyaltyLevelForm;
use App\Filament\Resources\LoyaltyLevels\Tables\LoyaltyLevelsTable;
use App\Models\LoyaltyLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LoyaltyLevelResource extends Resource
{
    protected static ?string $model = LoyaltyLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'level_number';

    protected static ?string $navigationLabel = 'Niveles de fidelidad';
    protected static ?string $modelLabel = 'nivel de fidelidad';
    protected static ?string $pluralModelLabel = 'niveles de fidelidad';

    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';

    public static function form(Schema $schema): Schema
    {
        return LoyaltyLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoyaltyLevelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyLevels::route('/'),
            'create' => CreateLoyaltyLevel::route('/create'),
            'edit' => EditLoyaltyLevel::route('/{record}/edit'),
        ];
    }
}