<?php

namespace App\Filament\Resources\LenderProducts\Pages;

use App\Filament\Resources\LenderProducts\LenderProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLenderProduct extends EditRecord
{
    protected static string $resource = LenderProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
