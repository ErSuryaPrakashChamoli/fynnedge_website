<?php

namespace App\Filament\Resources\MarketingSections;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\MarketingSections\Pages\CreateMarketingSection;
use App\Filament\Resources\MarketingSections\Pages\EditMarketingSection;
use App\Filament\Resources\MarketingSections\Pages\ListMarketingSections;
use App\Filament\Resources\MarketingSections\Schemas\MarketingSectionForm;
use App\Filament\Resources\MarketingSections\Tables\MarketingSectionsTable;
use App\Models\MarketingSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MarketingSectionResource extends Resource
{
    protected static ?string $model = MarketingSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Marketing Sections';

    public static function form(Schema $schema): Schema
    {
        return MarketingSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingSectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RestorableAuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingSections::route('/'),
            'create' => CreateMarketingSection::route('/create'),
            'edit' => EditMarketingSection::route('/{record}/edit'),
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
