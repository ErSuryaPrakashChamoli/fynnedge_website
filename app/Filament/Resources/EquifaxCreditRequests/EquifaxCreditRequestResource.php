<?php

namespace App\Filament\Resources\EquifaxCreditRequests;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\EquifaxCreditRequests\Pages\ListEquifaxCreditRequests;
use App\Filament\Resources\EquifaxCreditRequests\Pages\ViewEquifaxCreditRequest;
use App\Filament\Resources\EquifaxCreditRequests\Schemas\EquifaxCreditRequestInfolist;
use App\Filament\Resources\EquifaxCreditRequests\Tables\EquifaxCreditRequestsTable;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function Filament\Support\original_request;

/**
 * Every paid Equifax request — score or full report — with its full report
 * for authorised staff. Its own resource and its own "Equifax" navigation
 * group, separate from CRIF. A window and a workflow, not an editor: there is
 * no create, edit or delete. Requests start from a customer or application (or
 * this list's buttons) through RequestEquifaxCheck; only the workflow actions
 * (retry, reconcile, fresh, PDF) move one on.
 *
 * One resource, three menu entries: all requests, full reports (tab=reports)
 * and failed requests (tab=failed) — tabs of the same list, so filters and
 * actions behave the same everywhere.
 */
class EquifaxCreditRequestResource extends Resource
{
    protected static ?string $model = EquifaxCreditRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Equifax';

    protected static ?string $navigationLabel = 'Equifax Requests';

    protected static ?string $modelLabel = 'Equifax request';

    protected static ?string $pluralModelLabel = 'Equifax requests';

    protected static ?string $slug = 'equifax-requests';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return EquifaxCreditRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquifaxCreditRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'requestedBy']);
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        if ($items === []) {
            return [];
        }

        $listUrl = static::getUrl('index');
        $onTab = fn (string $tab): bool => original_request()->routeIs(static::getRouteBaseName().'.index') && original_request()->query('tab') === $tab;

        $items[0]->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.*') && ! $onTab('reports') && ! $onTab('failed'));

        return [
            NavigationItem::make('Equifax Credit Reports')
                ->key(static::class.':reports')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedDocumentChartBar)
                ->sort(2)
                ->isActiveWhen(fn (): bool => $onTab('reports'))
                ->url($listUrl.'?tab=reports'),
            ...$items,
            NavigationItem::make('Equifax Failed Requests')
                ->key(static::class.':failed')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->sort(4)
                ->badge(fn (): ?string => ($count = EquifaxCreditRequest::query()->where('status', EquifaxRequestStatus::ReconciliationRequired)->count()) > 0 ? (string) $count : null, color: 'warning')
                ->isActiveWhen(fn (): bool => $onTab('failed'))
                ->url($listUrl.'?tab=failed'),
        ];
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
            'index' => ListEquifaxCreditRequests::route('/'),
            'view' => ViewEquifaxCreditRequest::route('/{record}'),
        ];
    }
}
