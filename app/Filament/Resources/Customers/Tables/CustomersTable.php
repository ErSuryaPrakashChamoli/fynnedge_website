<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('journey_sessions_count')
                    ->label('Applications')
                    ->counts('journeySessions')
                    ->alignCenter(),
                TextColumn::make('latestCrifReport.status')
                    ->label('CRIF')
                    ->badge()
                    ->placeholder('Not requested')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('viewAny', CrifReport::class)),
                TextColumn::make('latestEquifaxRequest.status')
                    ->label('Equifax')
                    ->badge()
                    ->placeholder('Not requested')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('viewAny', EquifaxCreditRequest::class)),
                TextColumn::make('latestExperianRequest.status')
                    ->label('Experian')
                    ->badge()
                    ->placeholder('Not requested')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('viewAny', ExperianCreditRequest::class)),
                TextColumn::make('latestCibilRequest.status')
                    ->label('CIBIL')
                    ->badge()
                    ->placeholder('Not requested')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('viewAny', CibilCreditRequest::class)),
                TextColumn::make('created_at')
                    ->label('First seen')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
