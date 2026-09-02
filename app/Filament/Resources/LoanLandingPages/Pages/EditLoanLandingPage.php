<?php

namespace App\Filament\Resources\LoanLandingPages\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Models\LoanLandingPage;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLoanLandingPage extends EditRecord
{
    use HasPreviewAction;

    protected static string $resource = LoanLandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(
                'loans.landing-pages.show',
                fn (LoanLandingPage $record) => ['loanProduct' => $record->loanProduct, 'landingPage' => $record],
            ),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
