<?php

namespace App\Filament\Resources\Employers\Pages;

use App\Filament\Imports\EmployerRatingImporter;
use App\Filament\Resources\Employers\EmployerResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployers extends ListRecords
{
    protected static string $resource = EmployerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->label('Bulk upload employers')
                ->importer(EmployerRatingImporter::class),
        ];
    }
}
