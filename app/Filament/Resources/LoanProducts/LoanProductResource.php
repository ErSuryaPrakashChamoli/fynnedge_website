<?php

namespace App\Filament\Resources\LoanProducts;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\LoanProducts\Pages\CreateLoanProduct;
use App\Filament\Resources\LoanProducts\Pages\EditLoanProduct;
use App\Filament\Resources\LoanProducts\Pages\ListLoanProducts;
use App\Filament\Resources\LoanProducts\RelationManagers\LenderProductsRelationManager;
use App\Filament\Resources\LoanProducts\Schemas\LoanProductForm;
use App\Filament\Resources\LoanProducts\Tables\LoanProductsTable;
use App\Models\LoanProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoanProductResource extends Resource
{
    protected static ?string $model = LoanProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return LoanProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LenderProductsRelationManager::class,
            FaqsRelationManager::class,
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoanProducts::route('/'),
            'create' => CreateLoanProduct::route('/create'),
            'edit' => EditLoanProduct::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
