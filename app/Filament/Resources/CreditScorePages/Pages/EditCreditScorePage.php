<?php

namespace App\Filament\Resources\CreditScorePages\Pages;

use App\Filament\Pages\CreditScorePageSettings;
use App\Filament\Resources\CreditScorePages\CreditScorePageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCreditScorePage extends EditRecord
{
    protected static string $resource = CreditScorePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('headingAndSeo')
                ->label('Heading & SEO')
                ->color('gray')
                ->visible(fn (): bool => CreditScorePageSettings::canAccess())
                ->url(fn (): string => CreditScorePageSettings::getUrl(['bureau' => $this->getRecord()->bureau->value])),
            Action::make('view')
                ->label('View page')
                ->color('gray')
                ->url(fn (): string => route('credit-score.show', ['bureau' => $this->getRecord()->bureau]), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
