<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientActions;
use App\Filament\Resources\ApiClients\ApiClientResource;
use App\Models\ApiClient;
use App\Support\ApiClients\RegisterApiClient;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The role field is not a column: it is the service account's role, read in
 * on fill and synced back on save, together with the account's name.
 */
class EditApiClient extends EditRecord
{
    protected static string $resource = ApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApiClientActions::generateKey(),
            ApiClientActions::revoke(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ApiClient $client */
        $client = $this->getRecord();
        $data['role'] = $client->user?->roles->first()?->name;

        return $data;
    }

    /**
     * @param  ApiClient  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): ApiClient {
            $record->update(Arr::except($data, ['role']));
            $record->user->forceFill(['name' => RegisterApiClient::accountName($record->name)])->save();
            $record->user->syncRoles([(string) $data['role']]);

            return $record;
        });
    }
}
