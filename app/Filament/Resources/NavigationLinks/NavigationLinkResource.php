<?php

namespace App\Filament\Resources\NavigationLinks;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\NavigationLinks\Pages\CreateNavigationLink;
use App\Filament\Resources\NavigationLinks\Pages\EditNavigationLink;
use App\Filament\Resources\NavigationLinks\Pages\ListNavigationLinks;
use App\Filament\Resources\NavigationLinks\Schemas\NavigationLinkForm;
use App\Filament\Resources\NavigationLinks\Tables\NavigationLinksTable;
use App\Models\NavigationLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NavigationLinkResource extends Resource
{
    protected static ?string $model = NavigationLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Navigation Links';

    public static function form(Schema $schema): Schema
    {
        return NavigationLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NavigationLinksTable::configure($table);
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
            'index' => ListNavigationLinks::route('/'),
            'create' => CreateNavigationLink::route('/create'),
            'edit' => EditNavigationLink::route('/{record}/edit'),
        ];
    }
}
