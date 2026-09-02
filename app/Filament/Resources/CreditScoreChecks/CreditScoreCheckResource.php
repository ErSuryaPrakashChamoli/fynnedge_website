<?php

namespace App\Filament\Resources\CreditScoreChecks;

use App\Filament\Resources\CreditScoreChecks\Pages\ListCreditScoreChecks;
use App\Filament\Resources\CreditScoreChecks\Pages\ViewCreditScoreCheck;
use App\Filament\Resources\CreditScoreChecks\Schemas\CreditScoreCheckForm;
use App\Filament\Resources\CreditScoreChecks\Tables\CreditScoreChecksTable;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CreditScoreCheckResource extends Resource
{
    protected static ?string $model = CreditScoreCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Credit Score Checks';

    protected static ?string $modelLabel = 'credit score check';

    public static function form(Schema $schema): Schema
    {
        return CreditScoreCheckForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditScoreChecksTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditScoreChecks::route('/'),
            'view' => ViewCreditScoreCheck::route('/{record}'),
        ];
    }
}
