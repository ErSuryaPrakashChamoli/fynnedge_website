<?php

namespace App\Filament\Resources\CibilCreditRequests\Schemas;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * The request screen is one Blade view (resources/views/filament/cibil/request.blade.php)
 * rather than a stack of infolist entries: CIBIL's report is deeply nested
 * and irregular, and the view renders every field Surepass sent — including
 * ones nothing was written for — through App\Modules\Cibil\Services\CibilReportDocument.
 */
class CibilCreditRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.cibil.request')->columnSpanFull(),
            ]);
    }
}
