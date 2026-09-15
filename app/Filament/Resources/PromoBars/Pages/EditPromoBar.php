<?php

namespace App\Filament\Resources\PromoBars\Pages;

use App\Filament\Resources\PromoBars\PromoBarResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoBar extends EditRecord
{
    protected static string $resource = PromoBarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
