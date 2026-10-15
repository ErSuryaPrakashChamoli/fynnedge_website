<?php

namespace App\Filament\Resources\CrifReports\Schemas;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * The report screen is one Blade view (resources/views/filament/crif-reports/report.blade.php)
 * rather than a stack of infolist entries: CRIF's sections are irregular and
 * nested, and the view renders every field the provider sent — including ones
 * no entry was written for — via App\Modules\CreditBureau\Services\CrifReportDocument.
 */
class CrifReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.crif-reports.report')->columnSpanFull(),
            ]);
    }
}
