<?php

namespace App\Filament\Resources\JourneySessions;

use App\Filament\Resources\JourneySessions\Pages\ListJourneySessions;
use App\Filament\Resources\JourneySessions\Pages\ViewJourneySession;
use App\Filament\Resources\JourneySessions\RelationManagers\ResponsesRelationManager;
use App\Filament\Resources\JourneySessions\Schemas\JourneySessionForm;
use App\Filament\Resources\JourneySessions\Tables\JourneySessionsTable;
use App\Modules\Journey\Models\JourneySession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class JourneySessionResource extends Resource
{
    protected static ?string $model = JourneySession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'application';

    public static function form(Schema $schema): Schema
    {
        return JourneySessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JourneySessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ResponsesRelationManager::class,
        ];
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
            'index' => ListJourneySessions::route('/'),
            'view' => ViewJourneySession::route('/{record}'),
        ];
    }
}
