<?php

namespace App\Filament\Resources\ExperianCreditRequests\Pages;

use App\Filament\Resources\ExperianCreditRequests\ExperianActions;
use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListExperianCreditRequests extends ListRecords
{
    protected static string $resource = ExperianCreditRequestResource::class;

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
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', ExperianProduct::Report)),
            'scores' => Tab::make('Scores')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', ExperianProduct::Score)),
            'pdfs' => Tab::make('PDF reports')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', ExperianProduct::Pdf)),
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ExperianRequestStatus::Failed, ExperianRequestStatus::ReconciliationRequired])),
            'reconciliation' => Tab::make('Needs reconciliation')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExperianRequestStatus::ReconciliationRequired))
                ->badge(fn (): ?int => ExperianCreditRequest::query()->where('status', ExperianRequestStatus::ReconciliationRequired)->count() ?: null),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ExperianActions::requestForCustomer(ExperianProduct::Report),
            ExperianActions::requestForCustomer(ExperianProduct::Score),
            ExperianActions::requestForCustomer(ExperianProduct::Pdf),
        ];
    }
}
