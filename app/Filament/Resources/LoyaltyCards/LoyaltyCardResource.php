<?php

namespace App\Filament\Resources\LoyaltyCards;

use App\Filament\Resources\LoyaltyCards\Pages\CreateLoyaltyCard;
use App\Filament\Resources\LoyaltyCards\Pages\EditLoyaltyCard;
use App\Filament\Resources\LoyaltyCards\Pages\ListLoyaltyCards;
use App\Filament\Resources\LoyaltyCards\RelationManagers\CustomerCardsRelationManager;
use App\Filament\Resources\LoyaltyCards\Schemas\LoyaltyCardForm;
use App\Filament\Resources\LoyaltyCards\Tables\LoyaltyCardsTable;
use App\Models\LoyaltyCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LoyaltyCardResource extends Resource
{
    protected static ?string $model = LoyaltyCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Tarjetas de fidelidad';
    protected static ?string $modelLabel = 'tarjeta de fidelidad';
    protected static ?string $pluralModelLabel = 'tarjetas de fidelidad';

    public static function form(Schema $schema): Schema
    {
        return LoyaltyCardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoyaltyCardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CustomerCardsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyCards::route('/'),
            'create' => CreateLoyaltyCard::route('/create'),
            'edit' => EditLoyaltyCard::route('/{record}/edit'),
        ];
    }
}