<?php

namespace App\Filament\Resources\CreditScoreChecks\Tables;

use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
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
                TextColumn::make('provider')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state, $record): string => match (true) {
                        $state !== 'surepass' => 'Demo',
                        $record->sourceBureau() !== $record->bureau => 'Live ('.$record->sourceBureau()?->getLabel().' via Surepass)',
                        default => 'Live (Surepass)',
                    })
                    ->color(fn (string $state): string => $state === 'surepass' ? 'success' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn ($record): ?string => $record->liveFailure()?->getLabel())
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
                Action::make('viewReport')
                    ->label('Report')
                    ->tooltip(fn (CreditScoreCheck $record): string => 'Full report, kept with the '.$record->sourceBureau()?->getLabel().' requests')
                    ->icon(Heroicon::OutlinedDocumentChartBar)
                    ->visible(fn (CreditScoreCheck $record): bool => CreditScoreCheckResource::bureauReportUrl($record) !== null)
                    ->url(fn (CreditScoreCheck $record): ?string => CreditScoreCheckResource::bureauReportUrl($record)),
            ]);
    }
}
