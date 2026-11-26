<?php

namespace App\Filament\Resources\DebtClosures;

use App\Modules\DebtConsolidation\Actions\RecordClosurePayment;
use App\Modules\DebtConsolidation\Actions\UpdateClosureStatus;
use App\Modules\DebtConsolidation\Enums\ClosurePaymentMode;
use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtClosurePayment;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The actions that move a closure: record a payment, and update the
 * closure status with the evidence UpdateClosureStatus requires.
 */
final class ClosureActions
{
    public static function updateStatus(): Action
    {
        return Action::make('closureStatus')
            ->label('Update closure')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (DebtClosure $record): bool => $record->status !== ClosureStatus::ClosureVerified && (bool) auth()->user()?->can('update', $record))
            ->fillForm(fn (DebtClosure $record): array => ['status' => $record->status, 'final_closure_amount' => $record->final_closure_amount ?? ($record->isFullClosure() ? ($record->quotation ?? $record->liability->currentQuotation())?->total_amount : null), 'noc_document_id' => $record->noc_document_id])
            ->schema([
                Select::make('status')->options(ClosureStatus::class)->required(),
                TextInput::make('final_closure_amount')->label('Final closure amount')->numeric()->prefix('₹'),
                Select::make('noc_document_id')->label('NOC / closure letter')
                    ->options(fn (DebtClosure $record) => $record->application->documents()->get()->mapWithKeys(fn (DebtDocument $document): array => [$document->id => $document->title().' v'.$document->version.' ('.$document->status->value.')']))
                    ->helperText('Upload it in the case Documents tab, then verify it.'),
                Textarea::make('note')->maxLength(1000),
            ])
            ->action(function (DebtClosure $record, array $data): void {
                try {
                    app(UpdateClosureStatus::class)->handle(
                        $record,
                        $data['status'] instanceof ClosureStatus ? $data['status'] : ClosureStatus::from($data['status']),
                        auth()->id(),
                        $data['note'] ?? null,
                        filled($data['final_closure_amount'] ?? null) ? (float) $data['final_closure_amount'] : null,
                        filled($data['noc_document_id'] ?? null) ? DebtDocument::query()->find($data['noc_document_id']) : null,
                    );
                    Notification::make()->title('Closure updated')->success()->send();
                } catch (ValidationException $exception) {
                    Notification::make()->title('Not saved')->body(implode(' ', $exception->validator->errors()->all()))->danger()->persistent()->send();
                }
            });
    }

    public static function recordPayment(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->visible(fn (DebtClosure $record): bool => ! $record->status->isFinished() && (bool) auth()->user()?->can('update', $record))
            ->schema([
                TextInput::make('amount')->numeric()->prefix('₹')->required()->minValue(1),
                DatePicker::make('paid_on')->required()->default(now()),
                Select::make('payment_mode')->options(ClosurePaymentMode::class)->required()->default(ClosurePaymentMode::LenderDirect->value),
                Select::make('paid_by')->options([DebtClosurePayment::PAID_BY_NEW_LENDER => 'New lender', DebtClosurePayment::PAID_BY_CUSTOMER => 'Customer'])->required()->default(DebtClosurePayment::PAID_BY_NEW_LENDER),
                TextInput::make('utr_reference')->label('UTR / reference')->maxLength(60),
                Select::make('debt_document_id')->label('Payment proof')->options(fn (DebtClosure $record) => $record->application->documents()->get()->mapWithKeys(fn (DebtDocument $document): array => [$document->id => $document->title().' v'.$document->version])),
                Textarea::make('notes')->maxLength(1000),
            ])
            ->action(function (DebtClosure $record, array $data): void {
                app(RecordClosurePayment::class)->record($record, [...$data, 'payment_mode' => $data['payment_mode'] instanceof ClosurePaymentMode ? $data['payment_mode']->value : $data['payment_mode']], auth()->id());
                Notification::make()->title('Payment recorded — confirm it once the old lender has received it')->success()->send();
            });
    }
}
