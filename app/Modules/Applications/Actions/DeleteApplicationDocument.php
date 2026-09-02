<?php

namespace App\Modules\Applications\Actions;

use App\Modules\Applications\Models\ApplicationDocument;

class DeleteApplicationDocument
{
    public function handle(ApplicationDocument $document): void
    {
        $document->deleteStoredFile();
        $document->delete();
    }
}
