<?php

namespace App\Filament\Resources\CalculatorPages;

use App\Filament\Resources\CalculatorPages\Pages\CreateCalculatorPage;
use App\Filament\Resources\CalculatorPages\Pages\EditCalculatorPage;
use App\Filament\Resources\CalculatorPages\Pages\ListCalculatorPages;
use App\Filament\Resources\CalculatorPages\Schemas\CalculatorPageForm;
use App\Filament\Resources\CalculatorPages\Tables\CalculatorPagesTable;
use App\Models\CalculatorPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CalculatorPageResource extends Resource
{
    protected static ?string $model = CalculatorPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Calculator Pages';

    public static function form(Schema $schema): Schema
    {
        return CalculatorPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalculatorPagesTable::configure($table);
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
            'index' => ListCalculatorPages::route('/'),
            'create' => CreateCalculatorPage::route('/create'),
            'edit' => EditCalculatorPage::route('/{record}/edit'),
        ];
    }
}
