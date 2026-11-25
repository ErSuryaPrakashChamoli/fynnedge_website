<?php

namespace App\Filament\Resources\LoanLandingPages\Pages;

use App\Filament\Pages\CreateWebsitePage;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLoanLandingPages extends ListRecords
{
    protected static string $resource = LoanLandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // "Which kind of page do I need?" — the guided Create a Page screen.
            Action::make('pageTypes')
                ->label('Page types guide')
                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                ->color('gray')
                ->url(fn (): string => CreateWebsitePage::getUrl())
                ->visible(fn (): bool => CreateWebsitePage::canAccess()),
            CreateAction::make(),
        ];
    }
}
