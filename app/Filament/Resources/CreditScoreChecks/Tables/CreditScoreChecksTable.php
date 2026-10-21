<?php

namespace App\Filament\Resources\CreditScoreChecks\Tables;

use App\Filament\Resources\CreditScoreChecks\CreditScoreCheckResource;
use App\Filament\Schemas\IndiaDateRangeFilter;
use App\Models\User;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Enums\ScoreBand;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Services\CreditScoreChecksCsv;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                TextColumn::make('created_at')
                    ->label('Date of application (IST)')
                    ->dateTime()
                    ->timezone(IndiaDateRangeFilter::TIMEZONE)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('bureau')->options(BureauName::class)->multiple(),
                SelectFilter::make('status')->options(CreditCheckStatus::class),
                IndiaDateRangeFilter::make('created_at', 'Applied'),
                Filter::make('score')
                    ->schema([
                        TextInput::make('min')->label('Score from')->integer()->minValue(ScoreBand::LOWEST_SCORE)->maxValue(ScoreBand::HIGHEST_SCORE)->placeholder((string) ScoreBand::LOWEST_SCORE),
                        TextInput::make('max')->label('Score to')->integer()->minValue(ScoreBand::LOWEST_SCORE)->maxValue(ScoreBand::HIGHEST_SCORE)->placeholder((string) ScoreBand::HIGHEST_SCORE),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $query) => $query->where('score', '>=', (int) $data['min']))
                        ->when(filled($data['max'] ?? null), fn (Builder $query) => $query->where('score', '<=', (int) $data['max'])))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        filled($data['min'] ?? null) ? 'Score from '.$data['min'] : null,
                        filled($data['max'] ?? null) ? 'Score to '.$data['max'] : null,
                    ])),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->toolbarActions([
                BulkAction::make('downloadSelected')
                    ->label('Download selected (CSV)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records): StreamedResponse => CreditScoreChecksCsv::download(
                        CreditScoreCheck::query()->whereKey($records->modelKeys()),
                        ($user = auth()->user()) instanceof User ? $user : null,
                    )),
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
