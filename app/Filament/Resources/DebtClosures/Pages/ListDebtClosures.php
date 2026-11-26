<?php

namespace App\Filament\Resources\DebtClosures\Pages;

use App\Filament\Resources\DebtClosures\DebtClosureResource;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDebtClosures extends ListRecords
{
    protected static string $resource = DebtClosureResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Closure pending')->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', [ClosureStatus::ClosureVerified, ClosureStatus::Cancelled])),
            'noc' => Tab::make('NOC awaited')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ClosureStatus::ClosureRequested, ClosureStatus::NocAwaited])),
            'verified' => Tab::make('Verified')->modifyQueryUsing(fn (Builder $query) => $query->where('status', ClosureStatus::ClosureVerified)),
            'all' => Tab::make('All'),
        ];
    }
}
