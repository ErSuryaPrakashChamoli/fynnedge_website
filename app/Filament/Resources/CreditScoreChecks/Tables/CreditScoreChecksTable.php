<?php

namespace App\Filament\Resources\CreditScoreChecks\Tables;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CreditScoreChecksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bureau')
                    ->badge(),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->description(fn ($record) => $record->mobile_number),
                TextColumn::make('pan_number')
                    ->label('PAN')
                    ->searchable(),
                TextColumn::make('score'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CreditCheckStatus $state) => match ($state) {
                        CreditCheckStatus::Completed => 'success',
                        CreditCheckStatus::Pending => 'warning',
                        CreditCheckStatus::Failed => 'danger',
                        CreditCheckStatus::NotRequested => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('bureau')->options(BureauName::class),
                SelectFilter::make('status')->options(CreditCheckStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
