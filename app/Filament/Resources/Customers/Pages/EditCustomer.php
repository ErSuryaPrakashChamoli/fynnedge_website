<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\CibilCreditRequests\CibilActions;
use App\Filament\Resources\CrifReports\CrifReportActions;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxActions;
use App\Filament\Resources\ExperianCreditRequests\ExperianActions;
use App\Modules\Customers\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CrifReportActions::viewExisting(),
            CrifReportActions::fetch(),
            CrifReportActions::fetchFresh(),
            EquifaxActions::group(),
            ExperianActions::group(),
            CibilActions::group(),
            self::portalActions(),
            DeleteAction::make(),
        ];
    }

    /**
     * Customer-portal controls, under the same Update:Customer permission as
     * editing the record. Each change is written to the customer's History.
     */
    private static function portalActions(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('verifyIdentity')
                ->label('Mark identity as verified')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->modalDescription('Confirm you have checked this customer\'s KYC documents. Their name, date of birth and gender then become read-only for them in the portal. A credit report is not proof of identity.')
                ->requiresConfirmation()
                ->visible(fn (Customer $record): bool => $record->identity_verified_at === null && (bool) Auth::user()?->can('update', $record))
                ->action(function (Customer $record): void {
                    $record->forceFill(['identity_verified_at' => now()])->save();
                    $record->recordEvent('identity_verified', ['source' => 'staff'], Auth::id());
                    Notification::make()->title('Identity marked as verified')->success()->send();
                }),
            Action::make('disablePortal')
                ->label('Disable portal sign-in')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->modalDescription('The customer is signed out at their next request and cannot sign in again until access is re-enabled. Their data is kept.')
                ->requiresConfirmation()
                ->visible(fn (Customer $record): bool => $record->portal_activated_at !== null && $record->portal_disabled_at === null && (bool) Auth::user()?->can('update', $record))
                ->action(function (Customer $record): void {
                    $record->forceFill(['portal_disabled_at' => now()])->save();
                    $record->recordEvent('portal_disabled', [], Auth::id());
                    Notification::make()->title('Portal sign-in disabled')->success()->send();
                }),
            Action::make('enablePortal')
                ->label('Re-enable portal sign-in')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->requiresConfirmation()
                ->visible(fn (Customer $record): bool => $record->portal_disabled_at !== null && (bool) Auth::user()?->can('update', $record))
                ->action(function (Customer $record): void {
                    $record->forceFill(['portal_disabled_at' => null])->save();
                    $record->recordEvent('portal_enabled', [], Auth::id());
                    Notification::make()->title('Portal sign-in re-enabled')->success()->send();
                }),
        ])
            ->label('Portal account')
            ->icon(Heroicon::OutlinedUserCircle)
            ->button();
    }
}
