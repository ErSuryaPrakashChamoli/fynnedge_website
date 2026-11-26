<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Debt Consolidation → Customers: the existing customer records (Leads &
 * Enquiries → Customers) that have a debt consolidation case. No second
 * customer list — each row opens the same customer record.
 */
class DebtConsolidationCustomers extends Page implements HasTable
{
    use BelongsToAdminModule, InteractsWithTable;

    protected string $view = 'filament.debt-consolidation.table-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $title = 'Debt Consolidation Customers';

    protected static ?string $slug = 'debt-consolidation-customers';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:DebtApplication') && (bool) auth()->user()?->can('ViewAny:Customer');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query()->whereIn('id', DebtApplication::query()->whereNotNull('customer_id')->select('customer_id')))
            ->columns([
                TextColumn::make('full_name')->label('Name')->searchable()->placeholder('—'),
                TextColumn::make('verified_mobile')->label('Verified mobile')->formatStateUsing(fn (?string $state): string => $state ? '+91 '.$state : '—')->searchable(),
                TextColumn::make('city')->placeholder('—'),
                TextColumn::make('cases')->label('Cases')->state(fn (Customer $record): string => DebtApplication::query()->where('customer_id', $record->id)->pluck('reference')->implode(', ')),
                TextColumn::make('last_login_at')->label('Last portal sign-in')->since()->placeholder('Never'),
            ])
            ->recordActions([
                Action::make('customer')->label('Customer record')->url(fn (Customer $record): string => CustomerResource::getUrl('edit', ['record' => $record]))->visible(fn (): bool => (bool) auth()->user()?->can('ViewAny:Customer')),
                Action::make('case')->label('Latest case')->url(fn (Customer $record): string => DebtApplicationResource::getUrl('view', ['record' => DebtApplication::query()->where('customer_id', $record->id)->latest('id')->first()])),
            ]);
    }
}
