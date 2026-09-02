<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Route;

class EditPage extends EditRecord
{
    use HasPreviewAction;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Page has no single fixed public route — each static page (about,
            // privacy-policy, terms, ...) is wired up under a route name equal to
            // its own slug, with no URL parameter (the slug is baked in via
            // ->defaults() at route-registration time, see routes/web.php). A
            // slug with no matching route (e.g. a newly created page a route
            // hasn't been added for yet) simply hides the action rather than
            // generating a dead preview link.
            $this->previewAction(
                fn (Page $record) => Route::has($record->slug) ? $record->slug : null,
                fn (Page $record) => [],
            ),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
