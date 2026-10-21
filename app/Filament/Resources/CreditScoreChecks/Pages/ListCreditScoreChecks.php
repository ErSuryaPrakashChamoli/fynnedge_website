<?php

namespace App\Filament\Resources\CreditScoreChecks\Pages;

use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use App\Models\User;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Services\CreditScoreChecksCsv;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCreditScoreChecks extends ListRecords
{
    protected static string $resource = CreditScoreCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The checks in view (search, filters, sort), with location.
            Action::make('downloadCsv')
                ->label('Download CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => CreditScoreChecksCsv::download(
                    $this->getFilteredSortedTableQuery() ?? CreditScoreCheck::query(),
                    ($user = auth()->user()) instanceof User ? $user : null,
                )),
        ];
    }
}
