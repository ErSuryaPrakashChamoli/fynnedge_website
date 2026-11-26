<?php

namespace App\Filament\Resources\DebtClosures\RelationManagers;

use App\Modules\DebtConsolidation\Actions\RecordClosurePayment;
use App\Modules\DebtConsolidation\Models\DebtClosurePayment;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_on')->date('j M Y'),
                TextColumn::make('amount')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state, true))->alignEnd(),
                TextColumn::make('payment_mode')->badge()->color('gray'),
                TextColumn::make('paid_by'),
                TextColumn::make('utr_reference')->label('UTR')->placeholder('—'),
                TextColumn::make('document.label')->label('Proof')->placeholder('—'),
                TextColumn::make('confirmed_at')->label('Confirmed')->dateTime('j M Y', 'Asia/Kolkata')->placeholder('Not yet'),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (DebtClosurePayment $record): bool => $record->confirmed_at === null && (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->modalDescription('Confirm only once you have evidence the existing lender received this payment.')
                    ->action(function (DebtClosurePayment $record): void {
                        abort_unless((bool) auth()->user()?->can('update', $this->getOwnerRecord()), 403);

                        try {
                            app(RecordClosurePayment::class)->confirm($record, auth()->id());
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Not confirmed')->body(implode(' ', $exception->validator->errors()->all()))->danger()->send();
                        }
                    }),
            ]);
    }
}
