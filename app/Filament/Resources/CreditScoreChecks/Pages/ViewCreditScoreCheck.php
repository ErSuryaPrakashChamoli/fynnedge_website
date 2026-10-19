<?php

namespace App\Filament\Resources\CreditScoreChecks\Pages;

use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCreditScoreCheck extends ViewRecord
{
    protected static string $resource = CreditScoreCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewReport')
                ->label(fn (CreditScoreCheck $record): string => 'View '.$record->sourceBureau()?->getLabel().' report')
                ->icon(Heroicon::OutlinedDocumentChartBar)
                ->visible(fn (CreditScoreCheck $record): bool => CreditScoreCheckResource::bureauReportUrl($record) !== null)
                ->url(fn (CreditScoreCheck $record): ?string => CreditScoreCheckResource::bureauReportUrl($record)),
        ];
    }
}
