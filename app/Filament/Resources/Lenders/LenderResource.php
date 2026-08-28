<?php

namespace App\Filament\Resources\Lenders;

use App\Filament\Resources\Lenders\Pages\CreateLender;
use App\Filament\Resources\Lenders\Pages\EditLender;
use App\Filament\Resources\Lenders\Pages\ListLenders;
use App\Filament\Resources\Lenders\Schemas\LenderForm;
use App\Filament\Resources\Lenders\Tables\LendersTable;
use App\Models\Lender;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LenderResource extends Resource
{
    protected static ?string $model = Lender::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return LenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LendersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLenders::route('/'),
            'create' => CreateLender::route('/create'),
            'edit' => EditLender::route('/{record}/edit'),
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
