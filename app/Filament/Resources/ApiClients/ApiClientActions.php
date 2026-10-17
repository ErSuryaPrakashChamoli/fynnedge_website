<?php

namespace App\Filament\Resources\ApiClients;

use App\Models\ApiClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Key actions for an API client, shared by the list and the edit page. There
 * is no delete: a client is revoked, so the requests it made keep their
 * "requested by".
 */
class ApiClientActions
{
    public static function generateKey(): Action
    {
        return Action::make('generateKey')
            ->label(fn (ApiClient $record): string => $record->isActive() ? 'Generate new key' : 'Re-activate with a new key')
            ->icon(Heroicon::OutlinedKey)
            ->authorize('update')
            ->requiresConfirmation()
            ->modalHeading('Generate a new API key?')
            ->modalDescription('The current key stops working immediately, and a revoked client becomes active again. Put the new key into the other system straight away — it is shown only once.')
            ->modalSubmitActionLabel('Generate key')
            ->action(fn (ApiClient $record) => self::keyNotification($record, $record->issueKey())->send());
    }

    public static function revoke(): Action
    {
        return Action::make('revoke')
            ->label('Revoke')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (ApiClient $record): bool => $record->isActive())
            ->requiresConfirmation()
            ->modalHeading('Revoke this API client?')
            ->modalDescription('Its key stops working immediately; every call from that system is refused until a new key is generated. Requests it already made are kept.')
            ->modalSubmitActionLabel('Revoke')
            ->action(function (ApiClient $record): void {
                $record->revoke();

                Notification::make()->title($record->name.' revoked')->success()->send();
            });
    }

    /**
     * The one time a key is ever shown: FynnEdge keeps only its hash.
     */
    public static function keyNotification(ApiClient $client, string $key): Notification
    {
        return Notification::make()
            ->title('API key for '.$client->name)
            ->body('Copy it now and store it in the other system — it is shown only this once.<br><br><code>'.e($key).'</code><br><br>Send it as the header <code>Authorization: Bearer &lt;key&gt;</code>.')
            ->success()
            ->persistent();
    }
}
