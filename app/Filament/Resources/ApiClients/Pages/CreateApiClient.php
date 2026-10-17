<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientActions;
use App\Filament\Resources\ApiClients\ApiClientResource;
use App\Models\User;
use App\Support\ApiClients\RegisterApiClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateApiClient extends CreateRecord
{
    protected static string $resource = ApiClientResource::class;

    /**
     * The new key, for the one notification that shows it. Private, so it is
     * never part of the component state sent to the browser.
     */
    private ?string $issuedKey = null;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $creator = auth()->user();

        ['client' => $client, 'key' => $this->issuedKey] = app(RegisterApiClient::class)->handle(
            Arr::except($data, ['role']),
            (string) $data['role'],
            $creator instanceof User ? $creator : null,
        );

        return $client;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return ApiClientActions::keyNotification($this->getRecord(), (string) $this->issuedKey);
    }
}
