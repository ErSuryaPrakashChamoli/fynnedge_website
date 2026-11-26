<?php

namespace App\Filament\Resources\DebtApplications\Pages;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDebtApplications extends ListRecords
{
    protected static string $resource = DebtApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Open a case for a customer')];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $closed = [DebtApplicationStatus::Completed, DebtApplicationStatus::NotProceeding, DebtApplicationStatus::Withdrawn];

        return [
            'active' => Tab::make('Open cases')->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', $closed)->where('status', '!=', DebtApplicationStatus::Draft)),
            'submitted' => Tab::make('Submitted assessments')->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('submitted_at')->whereNotIn('status', $closed)),
            'drafts' => Tab::make('Unfinished drafts')->modifyQueryUsing(fn (Builder $query) => $query->where('status', DebtApplicationStatus::Draft)),
            'closed' => Tab::make('Closed')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $closed)),
            'all' => Tab::make('All'),
        ];
    }
}
