<?php

namespace App\Filament\Resources\CrifReports\Tables;

use App\Models\User;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\PanNumber;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CrifReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->placeholder('No customer record')
                    ->searchable()
                    ->description(fn (CrifReport $record): ?string => $record->customer?->email),
                TextColumn::make('pan_masked')
                    ->label('PAN'),
                TextColumn::make('mobile_masked')
                    ->label('Mobile')
                    ->placeholder('—'),
                TextColumn::make('score')
                    ->label('Score')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (CrifReport $record): ?string => $record->error_code?->getLabel()),
                TextColumn::make('request_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('requestedBy.name')
                    ->label('Initiated by')
                    ->placeholder('—'),
                TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->since()
                    ->sortable(),
                TextColumn::make('provider_report_id')
                    ->label('Report ID')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('duplicates_blocked')
                    ->label('Duplicates prevented')
                    ->state(fn (CrifReport $record): int => $record->duplicates_blocked + $record->times_reused)
                    ->tooltip(fn (CrifReport $record): string => "{$record->times_reused} reused, {$record->duplicates_blocked} blocked while in progress")
                    ->alignCenter(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                Filter::make('requested_at')
                    ->label('Request date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('requested_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('requested_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        ($data['from'] ?? null) ? 'From '.$data['from'] : null,
                        ($data['until'] ?? null) ? 'Until '.$data['until'] : null,
                    ])),
                SelectFilter::make('requested_by')
                    ->label('Employee')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', CrifReport::query()->select('requested_by')->whereNotNull('requested_by'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(CrifReportStatus::class)
                    ->multiple(),
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'full_name')
                    ->searchable(),
                // The PAN is stored encrypted, so it is matched by its keyed hash —
                // the typed PAN is never stored, logged or put in the URL.
                Filter::make('pan')
                    ->label('PAN')
                    ->schema([
                        TextInput::make('pan')
                            ->label('Full PAN')
                            ->placeholder('ABCDE1234F')
                            ->maxLength(12),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $pan = PanNumber::normalize($data['pan'] ?? null);

                        return $pan === '' ? $query : $query->where('pan_hash', PanNumber::isValid($pan) ? PanNumber::hash($pan) : '');
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['pan'] ?? null) ? 'PAN '.PanNumber::mask(PanNumber::normalize($data['pan'])) : null),
                TernaryFilter::make('outcome')
                    ->label('Outcome')
                    ->placeholder('All')
                    ->trueLabel('Successful')
                    ->falseLabel('Failed')
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('status', [CrifReportStatus::Success, CrifReportStatus::Expired]),
                        false: fn (Builder $query) => $query->where('status', CrifReportStatus::Failed),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('request_type')
                    ->label('Type')
                    ->options(CrifRequestType::class),
                TernaryFilter::make('reused')
                    ->label('Reused')
                    ->placeholder('All')
                    ->trueLabel('Reused at least once')
                    ->falseLabel('Never reused')
                    ->queries(
                        true: fn (Builder $query) => $query->where('times_reused', '>', 0),
                        false: fn (Builder $query) => $query->where('times_reused', 0),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
