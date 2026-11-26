<?php

namespace App\Filament\Resources\DebtCalculationSnapshots\Pages;

use App\Filament\Resources\DebtCalculationSnapshots\DebtCalculationSnapshotResource;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewDebtCalculationSnapshot extends ViewRecord
{
    protected static string $resource = DebtCalculationSnapshotResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var DebtCalculationSnapshot $snapshot */
        $snapshot = $this->getRecord();
        $view = fn (string $name): View => View::make('filament.debt-consolidation.case-'.$name)->viewData(['record' => $snapshot->application, 'snapshot' => $snapshot]);

        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Assessment')->schema([$view('assessment')]),
                Tab::make('Suitability')->schema([$view('suitability')]),
                Tab::make('Savings comparison')->schema([$view('savings')]),
                Tab::make('Foreclosure')->schema([$view('foreclosure')]),
            ]),
        ]);
    }
}
