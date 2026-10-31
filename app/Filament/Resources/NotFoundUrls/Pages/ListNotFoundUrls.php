<?php

namespace App\Filament\Resources\NotFoundUrls\Pages;

use App\Filament\Resources\NotFoundUrls\NotFoundUrlResource;
use Filament\Resources\Pages\ListRecords;

class ListNotFoundUrls extends ListRecords
{
    protected static string $resource = NotFoundUrlResource::class;

    public function getSubheading(): ?string
    {
        return 'Old or mistyped URLs that answered 404, most requested first. Scanner probes and missing asset files are not listed. '
            .'Fix a row with a rule in Redirects: a 301 to the page that replaced it, or 410 Gone if nothing did. '
            .'Never point an unrelated URL at the homepage. Paths not requested for '.config('seo.not_found.retention_days').' days drop off.';
    }
}
