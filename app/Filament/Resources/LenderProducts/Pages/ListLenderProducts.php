<?php

namespace App\Filament\Resources\LenderProducts\Pages;

use App\Filament\Imports\LenderProductImporter;
use App\Filament\Resources\LenderProducts\LenderProductResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListLenderProducts extends ListRecords
{
    protected static string $resource = LenderProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(LenderProductImporter::class)
                // One chunk for a typical file: the importer's lookup caches
                // live for a chunk, so fewer chunks means fewer repeat queries.
                ->chunkSize(500),
        ];
    }
}
