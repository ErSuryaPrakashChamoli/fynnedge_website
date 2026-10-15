<?php

namespace App\Filament\Resources\CrifReports;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\CrifReports\Pages\ListCrifReports;
use App\Filament\Resources\CrifReports\Pages\ViewCrifReport;
use App\Filament\Resources\CrifReports\Schemas\CrifReportInfolist;
use App\Filament\Resources\CrifReports\Tables\CrifReportsTable;
use App\Modules\CreditBureau\Models\CrifReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every paid CRIF report attempt, with its full report for authorised staff.
 *
 * A window and a workflow, not an editor: there is no create, edit or delete.
 * Requests start from a customer or application (or the list's "Fetch CRIF
 * report" button) through RequestCrifReport, and only the workflow actions
 * (retry, reconcile, fresh, PDF) move an attempt on. Access is the Shield
 * permissions in App\Modules\CreditBureau\Services\CrifRoles.
 */
class CrifReportResource extends Resource
{
    protected static ?string $model = CrifReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'Credit Bureau';

    protected static ?string $navigationLabel = 'CRIF Reports';

    protected static ?string $modelLabel = 'CRIF report';

    protected static ?string $pluralModelLabel = 'CRIF reports';

    protected static ?string $slug = 'crif-reports';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return CrifReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrifReportsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'requestedBy']);
    }

    public static function getRelations(): array
    {
        return [
            AuditLogsRelationManager::class,
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

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrifReports::route('/'),
            'view' => ViewCrifReport::route('/{record}'),
        ];
    }
}
