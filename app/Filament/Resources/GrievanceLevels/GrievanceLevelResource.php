<?php

namespace App\Filament\Resources\GrievanceLevels;

use App\Filament\Resources\GrievanceLevels\Pages\CreateGrievanceLevel;
use App\Filament\Resources\GrievanceLevels\Pages\EditGrievanceLevel;
use App\Filament\Resources\GrievanceLevels\Pages\ListGrievanceLevels;
use App\Filament\Resources\GrievanceLevels\Schemas\GrievanceLevelForm;
use App\Filament\Resources\GrievanceLevels\Tables\GrievanceLevelsTable;
use App\Models\GrievanceLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GrievanceLevelResource extends Resource
{
    protected static ?string $model = GrievanceLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Grievance Redressal Matrix';

    protected static ?string $modelLabel = 'grievance contact';

    public static function form(Schema $schema): Schema
    {
        return GrievanceLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GrievanceLevelsTable::configure($table);
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
            'index' => ListGrievanceLevels::route('/'),
            'create' => CreateGrievanceLevel::route('/create'),
            'edit' => EditGrievanceLevel::route('/{record}/edit'),
        ];
    }
}
