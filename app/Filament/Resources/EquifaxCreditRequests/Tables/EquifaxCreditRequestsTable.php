<?php

namespace App\Filament\Resources\EquifaxCreditRequests\Tables;

use App\Models\User;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EquifaxCreditRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->placeholder('No customer record')
                    ->searchable()
                    ->description(fn (EquifaxCreditRequest $record): ?string => $record->customer?->email),
                TextColumn::make('pan_masked')->label('PAN'),
                TextColumn::make('mobile_masked')->label('Mobile')->placeholder('—'),
                TextColumn::make('score')->label('Score')->placeholder('—')->sortable(),
                TextColumn::make('product')->label('Type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (EquifaxCreditRequest $record): ?string => $record->error_code?->getLabel()),
                TextColumn::make('request_type')->label('Request')->badge(),
                TextColumn::make('requestedBy.name')
                    ->label('Requested by')
                    ->state(fn (EquifaxCreditRequest $record): ?string => $record->requestedBy?->name ?? ($record->channel === 'website' ? 'Website visitor' : null))
                    ->placeholder('—'),
                TextColumn::make('requested_at')->label('Requested')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('completed_at')->label('Completed')->dateTime('d M Y, H:i')->placeholder('—')->sortable(),
                TextColumn::make('provider_report_id')->label('Provider reference')->placeholder('—')->searchable(),
                TextColumn::make('duplicates_blocked')
                    ->label('Duplicates prevented')
                    ->state(fn (EquifaxCreditRequest $record): int => $record->duplicates_blocked + $record->times_reused)
                    ->tooltip(fn (EquifaxCreditRequest $record): string => "{$record->times_reused} reused, {$record->duplicates_blocked} blocked while in progress")
                    ->alignCenter(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'full_name')
                    ->searchable(),
                // The PAN is stored encrypted, so it is matched by its keyed hash —
                // the typed PAN is never stored, logged or put in the URL.
                Filter::make('pan')
                    ->label('PAN')
                    ->schema([
                        TextInput::make('pan')->label('Full PAN')->placeholder('ABCDE1234F')->maxLength(12),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $pan = PanNumber::normalize($data['pan'] ?? null);

                        return $pan === '' ? $query : $query->where('pan_hash', PanNumber::isValid($pan) ? PanNumber::hash($pan) : '');
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['pan'] ?? null) ? 'PAN '.PanNumber::mask(PanNumber::normalize($data['pan'])) : null),
                SelectFilter::make('status')->options(EquifaxRequestStatus::class)->multiple(),
                SelectFilter::make('product')->label('Type')->options(EquifaxProduct::class),
                SelectFilter::make('request_type')->label('Request')->options(EquifaxRequestType::class),
                SelectFilter::make('channel')->label('Channel')->options(['admin' => 'Admin', 'api' => 'Internal API', 'website' => 'Website (public score page)']),
                SelectFilter::make('requested_by')
                    ->label('Employee')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', EquifaxCreditRequest::query()->select('requested_by')->whereNotNull('requested_by'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
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
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
