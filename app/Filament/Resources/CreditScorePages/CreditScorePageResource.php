<?php

namespace App\Filament\Resources\CreditScorePages;

use App\Filament\Resources\CreditScorePages\Pages\CreateCreditScorePage;
use App\Filament\Resources\CreditScorePages\Pages\EditCreditScorePage;
use App\Filament\Resources\CreditScorePages\Pages\ListCreditScorePages;
use App\Filament\Resources\CreditScorePages\Schemas\CreditScorePageForm;
use App\Filament\Resources\CreditScorePages\Tables\CreditScorePagesTable;
use App\Models\CreditScorePage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CreditScorePageResource extends Resource
{
    protected static ?string $model = CreditScorePage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Credit Score Pages';

    public static function form(Schema $schema): Schema
    {
        return CreditScorePageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditScorePagesTable::configure($table);
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
            'index' => ListCreditScorePages::route('/'),
            'create' => CreateCreditScorePage::route('/create'),
            'edit' => EditCreditScorePage::route('/{record}/edit'),
        ];
    }
}
