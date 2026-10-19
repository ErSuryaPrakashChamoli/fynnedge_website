<?php

namespace App\Filament\Resources\CibilCreditRequests\Pages;

use App\Filament\Resources\CibilCreditRequests\CibilActions;
use App\Filament\Resources\CibilCreditRequests\CibilCreditRequestResource;
use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListCibilCreditRequests extends ListRecords
{
    protected static string $resource = CibilCreditRequestResource::class;

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
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', CibilProduct::Report)),
            'scores' => Tab::make('Scores')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', CibilProduct::Score)),
            'pdfs' => Tab::make('PDF reports')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('product', CibilProduct::Pdf)),
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [CibilRequestStatus::Failed, CibilRequestStatus::ReconciliationRequired])),
            'reconciliation' => Tab::make('Needs reconciliation')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CibilRequestStatus::ReconciliationRequired))
                ->badge(fn (): ?int => CibilCreditRequest::query()->where('status', CibilRequestStatus::ReconciliationRequired)->count() ?: null),
        ];
    }

    protected function getHeaderActions(): array
    {
        $experianStandInReports = auth()->user()?->can('viewAny', ExperianCreditRequest::class)
            ? CibilCreditRequestResource::experianStandInReportCount()
            : 0;

        return [
            Action::make('experianStandInReports')
                ->label("CIBIL page reports from Experian ({$experianStandInReports})")
                ->tooltip('The Free CIBIL Score page was answered by Experian (stand-in bureau), so those full reports are kept as Experian requests.')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible($experianStandInReports > 0)
                ->url(ExperianCreditRequestResource::getUrl('index', ['tab' => 'reports'])),
            CibilActions::requestForCustomer(CibilProduct::Report),
            CibilActions::requestForCustomer(CibilProduct::Score),
            CibilActions::requestForCustomer(CibilProduct::Pdf),
        ];
    }
}
