<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomersCsv;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The customers in view (search, filters, sort).
            Action::make('downloadCsv')
                ->label('Download CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => CustomersCsv::download(
                    $this->getFilteredSortedTableQuery() ?? Customer::query(),
                    ($user = auth()->user()) instanceof User ? $user : null,
                )),
            CreateAction::make()->label('Add customer'),
        ];
    }
}
