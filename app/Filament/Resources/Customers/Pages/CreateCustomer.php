<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Adds a customer who has not applied on the website yet — e.g. a walk-in or
 * phone lead — so a CRIF report can be fetched for them. Opens the customer's
 * page afterwards, where "Fetch CRIF Report" is. A later website application
 * with the same email joins this record rather than creating a second one.
 */
class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getRedirectUrl(): string
    {
        return CustomerResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
