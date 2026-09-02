<?php

namespace App\Filament\Resources\CreditScoreChecks\Pages;

use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use Filament\Resources\Pages\ListRecords;

class ListCreditScoreChecks extends ListRecords
{
    protected static string $resource = CreditScoreCheckResource::class;
}
