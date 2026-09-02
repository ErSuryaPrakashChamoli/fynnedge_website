<?php

namespace App\Filament\Resources\MarketingSections\Pages;

use App\Filament\Resources\MarketingSections\MarketingSectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingSection extends EditRecord
{
    protected static string $resource = MarketingSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
