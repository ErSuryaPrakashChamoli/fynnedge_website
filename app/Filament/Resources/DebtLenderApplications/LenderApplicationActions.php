<?php

namespace App\Filament\Resources\DebtLenderApplications;

use App\Modules\DebtConsolidation\Actions\UpdateLenderApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

/**
 * "Record lender update": the one way a lender application's status
 * changes, with the evidence UpdateLenderApplicationStatus requires.
 */
final class LenderApplicationActions
{
    public static function updateStatus(): Action
    {
        $is = fn (Get $get, LenderApplicationStatus ...$statuses): bool => in_array($get->enum('status', LenderApplicationStatus::class, isNullable: true), $statuses, true);

        return Action::make('lenderStatus')
            ->label('Record lender update')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (DebtLenderApplication $record): bool => ! $record->status->isFinal() && (bool) auth()->user()?->can('update', $record))
            ->fillForm(fn (DebtLenderApplication $record): array => $record->only(['status', 'lender_reference', 'sanctioned_amount', 'sanctioned_rate', 'sanctioned_tenure_months', 'processing_fee_amount', 'disbursed_amount', 'disbursed_at']))
            ->schema([
                Select::make('status')->options(LenderApplicationStatus::class)->required()->live(),
                TextInput::make('lender_reference')->label('Lender\'s application number')->maxLength(100),
                TextInput::make('sanctioned_amount')->numeric()->prefix('₹')->visible(fn (Get $get): bool => $is($get, LenderApplicationStatus::Approved, LenderApplicationStatus::SanctionLetterIssued, LenderApplicationStatus::Disbursed)),
                TextInput::make('sanctioned_rate')->label('Sanctioned rate %')->numeric()->visible(fn (Get $get): bool => $is($get, LenderApplicationStatus::Approved, LenderApplicationStatus::SanctionLetterIssued, LenderApplicationStatus::Disbursed)),
                TextInput::make('sanctioned_tenure_months')->numeric()->visible(fn (Get $get): bool => $is($get, LenderApplicationStatus::Approved, LenderApplicationStatus::SanctionLetterIssued, LenderApplicationStatus::Disbursed)),
                TextInput::make('processing_fee_amount')->numeric()->prefix('₹')->visible(fn (Get $get): bool => $is($get, LenderApplicationStatus::SanctionLetterIssued, LenderApplicationStatus::Disbursed)),
                TextInput::make('disbursed_amount')->numeric()->prefix('₹')->visible(fn (Get $get): bool => $is($get, LenderApplicationStatus::Disbursed)),
                DateTimePicker::make('disbursed_at')->timezone('Asia/Kolkata')->visible(fn (Get $get): bool => $is($get, LenderApplicationStatus::Disbursed)),
                Textarea::make('rejection_reason')->label('Lender\'s reason')->visible(fn (Get $get): bool => $is($get, LenderApplicationStatus::Rejected)),
                Textarea::make('note')->label('How the lender communicated this')->helperText('Required for an approval, e.g. "Approval email from lender RM, 25 Nov".')->maxLength(1000),
            ])
            ->action(function (DebtLenderApplication $record, array $data): void {
                $status = $data['status'] instanceof LenderApplicationStatus ? $data['status'] : LenderApplicationStatus::from($data['status']);
                $note = $data['note'] ?? null;
                unset($data['status'], $data['note']);

                try {
                    app(UpdateLenderApplicationStatus::class)->handle($record, $status, auth()->id(), $note, $data);
                    Notification::make()->title('Lender update recorded')->success()->send();
                } catch (ValidationException $exception) {
                    Notification::make()->title('Not saved')->body(implode(' ', $exception->validator->errors()->all()))->danger()->persistent()->send();
                }
            });
    }
}
