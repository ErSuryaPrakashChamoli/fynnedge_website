<?php

namespace App\Filament\Resources\EquifaxCreditRequests\Pages;

use App\Filament\Resources\EquifaxCreditRequests\EquifaxActions;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEquifaxCreditRequests extends ListRecords
{
    protected static string $resource = EquifaxCreditRequestResource::class;

    /**
     * Redeclared WITHOUT ListRecords' #[Url(as: 'filters')]: the PAN filter
     * holds a full PAN, which must never land in the address bar, browser
     * history, a shared link or the web server's access log.
     *
     * @var array<string, mixed> | null
     */
    public ?array $tableFilters = null;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All requests'),
            'reports' => Tab::make('Full reports')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', EquifaxProduct::Report)),
            'scores' => Tab::make('Scores')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', EquifaxProduct::Score)),
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [EquifaxRequestStatus::Failed, EquifaxRequestStatus::ReconciliationRequired])),
            'reconciliation' => Tab::make('Needs reconciliation')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', EquifaxRequestStatus::ReconciliationRequired))
                ->badge(fn (): ?int => EquifaxCreditRequest::query()->where('status', EquifaxRequestStatus::ReconciliationRequired)->count() ?: null),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EquifaxActions::requestForCustomer(EquifaxProduct::Report),
            EquifaxActions::requestForCustomer(EquifaxProduct::Score),
        ];
    }
}
