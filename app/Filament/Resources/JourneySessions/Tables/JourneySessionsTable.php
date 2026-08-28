<?php

namespace App\Filament\Resources\JourneySessions\Tables;

use App\Modules\Journey\Enums\JourneySessionStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JourneySessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loanProduct.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn ($record) => $record->customer?->email),
                TextColumn::make('currentStep.title')
                    ->label('Current step'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (JourneySessionStatus $state) => match ($state) {
                        JourneySessionStatus::Completed => 'success',
                        JourneySessionStatus::InProgress => 'warning',
                        JourneySessionStatus::Abandoned => 'gray',
                    }),
                TextColumn::make('utm_source')->label('Source')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(JourneySessionStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
