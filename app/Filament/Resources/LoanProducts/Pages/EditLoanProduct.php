<?php

namespace App\Filament\Resources\LoanProducts\Pages;

use App\Filament\Concerns\HasPreviewAction;
use App\Filament\Resources\LoanProducts\LoanProductResource;
use App\Models\LoanProduct;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLoanProduct extends EditRecord
{
    use HasPreviewAction;

    protected static string $resource = LoanProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction('loans.show', fn (LoanProduct $record) => ['loanProduct' => $record]),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
