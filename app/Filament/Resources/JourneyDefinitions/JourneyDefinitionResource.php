<?php

namespace App\Filament\Resources\JourneyDefinitions;

use App\Filament\Resources\JourneyDefinitions\Pages\CreateJourneyDefinition;
use App\Filament\Resources\JourneyDefinitions\Pages\EditJourneyDefinition;
use App\Filament\Resources\JourneyDefinitions\Pages\ListJourneyDefinitions;
use App\Filament\Resources\JourneyDefinitions\RelationManagers\StepsRelationManager;
use App\Filament\Resources\JourneyDefinitions\Schemas\JourneyDefinitionForm;
use App\Filament\Resources\JourneyDefinitions\Tables\JourneyDefinitionsTable;
use App\Modules\Journey\Models\JourneyDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JourneyDefinitionResource extends Resource
{
    protected static ?string $model = JourneyDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Journeys';

    protected static ?string $modelLabel = 'journey';

    public static function form(Schema $schema): Schema
    {
        return JourneyDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JourneyDefinitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJourneyDefinitions::route('/'),
            'create' => CreateJourneyDefinition::route('/create'),
            'edit' => EditJourneyDefinition::route('/{record}/edit'),
        ];
    }
}
