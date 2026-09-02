<?php

namespace App\Filament\Resources\LenderProducts;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\LenderProducts\Pages\CreateLenderProduct;
use App\Filament\Resources\LenderProducts\Pages\EditLenderProduct;
use App\Filament\Resources\LenderProducts\Pages\ListLenderProducts;
use App\Filament\Resources\LenderProducts\Schemas\LenderProductForm;
use App\Filament\Resources\LenderProducts\Tables\LenderProductsTable;
use App\Models\LenderProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LenderProductResource extends Resource
{
    protected static ?string $model = LenderProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Lender Offers';

    public static function form(Schema $schema): Schema
    {
        return LenderProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LenderProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLenderProducts::route('/'),
            'create' => CreateLenderProduct::route('/create'),
            'edit' => EditLenderProduct::route('/{record}/edit'),
        ];
    }
}
