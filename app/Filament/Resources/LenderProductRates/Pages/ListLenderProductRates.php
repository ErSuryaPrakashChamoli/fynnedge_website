<?php

namespace App\Filament\Resources\LenderProductRates\Pages;

use App\Enums\RateStatus;
use App\Filament\Resources\LenderProductRates\LenderProductRateResource;
use App\Filament\Widgets\RateHealthOverview;
use App\Models\LenderProductRate;
use App\Support\Rates\RateAuditCsv;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListLenderProductRates extends ListRecords
{
    protected static string $resource = LenderProductRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New rate'),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => RateAuditCsv::download(
                    $this->getFilteredSortedTableQuery() ?? LenderProductRate::query(),
                )),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [RateHealthOverview::class];
    }

    /**
     * The views the team works from day to day; "Needs attention" is the
     * default because unverified, expiring and submitted rates are the work.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'attention' => Tab::make('Needs attention')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->needsVerification()
                    ->orWhere(fn (Builder $query) => $query->expiringSoon())
                    ->orWhere(fn (Builder $query) => $query->expired())
                    ->orWhere('status', RateStatus::PendingApproval))),
            'pending' => Tab::make('Awaiting approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RateStatus::PendingApproval))
                ->badge(fn (): ?int => LenderProductRate::query()->where('status', RateStatus::PendingApproval)->count() ?: null),
            'live' => Tab::make('Live on site')
                ->modifyQueryUsing(fn (Builder $query) => $query->live()),
            'drafts' => Tab::make('Drafts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RateStatus::Draft)),
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => $query->expired()),
            'history' => Tab::make('Archived versions')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RateStatus::Archived)),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'attention';
    }
}
