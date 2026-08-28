<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Modules\Applications\Enums\ApplicationStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn ($record) => $record->customer?->email),
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->searchable(),
                TextColumn::make('lenderProduct.loanProduct.name')->label('Product')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ApplicationStatus $state) => match ($state) {
                        ApplicationStatus::Draft, ApplicationStatus::LenderSelected, ApplicationStatus::DocumentsPending => 'gray',
                        ApplicationStatus::DocumentsSubmitted, ApplicationStatus::Submitted, ApplicationStatus::UnderReview => 'warning',
                        ApplicationStatus::Sanctioned, ApplicationStatus::AgreementPending, ApplicationStatus::DisbursalProcessing, ApplicationStatus::Disbursed => 'success',
                        ApplicationStatus::Rejected, ApplicationStatus::Withdrawn, ApplicationStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ApplicationStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
