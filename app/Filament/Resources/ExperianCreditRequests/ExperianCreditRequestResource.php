<?php

namespace App\Filament\Resources\ExperianCreditRequests;

use App\Filament\RelationManagers\AuditLogsRelationManager;
use App\Filament\Resources\ExperianCreditRequests\Pages\ListExperianCreditRequests;
use App\Filament\Resources\ExperianCreditRequests\Pages\ViewExperianCreditRequest;
use App\Filament\Resources\ExperianCreditRequests\Schemas\ExperianCreditRequestInfolist;
use App\Filament\Resources\ExperianCreditRequests\Tables\ExperianCreditRequestsTable;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;
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
 * Every paid Experian request — score, full report or PDF — with its full
 * report for authorised staff. Its own resource and its own "Experian"
 * navigation group, separate from CRIF and Equifax. A window and a workflow,
 * not an editor: there is no create, edit or delete. Requests start from a
 * customer or application (or this list's buttons) through
 * RequestExperianCheck; only the workflow actions (retry, reconcile, fresh,
 * PDF) move one on.
 *
 * One resource, five menu entries — credit reports (tab=reports), score
 * requests (tab=scores), PDF reports (tab=pdfs), all requests and failed
 * requests (tab=failed) — tabs of the same list, so filters and actions behave
 * the same everywhere.
 */
class ExperianCreditRequestResource extends Resource
{
    protected static ?string $model = ExperianCreditRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Experian';

    protected static ?string $navigationLabel = 'Experian Requests';

    protected static ?string $modelLabel = 'Experian request';

    protected static ?string $pluralModelLabel = 'Experian requests';

    protected static ?string $slug = 'experian-requests';

    protected static ?int $navigationSort = 5;

    /** @var array<string, array{label: string, icon: Heroicon, sort: int}> */
    private const TAB_NAVIGATION = [
        'reports' => ['label' => 'Experian Credit Reports', 'icon' => Heroicon::OutlinedDocumentChartBar, 'sort' => 2],
        'scores' => ['label' => 'Experian Score Requests', 'icon' => Heroicon::OutlinedChartBar, 'sort' => 3],
        'pdfs' => ['label' => 'Experian PDF Reports', 'icon' => Heroicon::OutlinedDocumentArrowDown, 'sort' => 4],
    ];

    public static function infolist(Schema $schema): Schema
    {
        return ExperianCreditRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExperianCreditRequestsTable::configure($table);
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

        $items[0]->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.*')
            && collect([...array_keys(self::TAB_NAVIGATION), 'failed'])->doesntContain(fn (string $tab): bool => $onTab($tab)));

        return [
            ...collect(self::TAB_NAVIGATION)->map(fn (array $item, string $tab): NavigationItem => NavigationItem::make($item['label'])
                ->key(static::class.':'.$tab)
                ->group(static::getNavigationGroup())
                ->icon($item['icon'])
                ->sort($item['sort'])
                ->isActiveWhen(fn (): bool => $onTab($tab))
                ->url($listUrl.'?tab='.$tab))->values()->all(),
            ...$items,
            NavigationItem::make('Experian Failed Requests')
                ->key(static::class.':failed')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->sort(6)
                ->badge(fn (): ?string => ($count = ExperianCreditRequest::query()->where('status', ExperianRequestStatus::ReconciliationRequired)->count()) > 0 ? (string) $count : null, color: 'warning')
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
            'index' => ListExperianCreditRequests::route('/'),
            'view' => ViewExperianCreditRequest::route('/{record}'),
        ];
    }
}
