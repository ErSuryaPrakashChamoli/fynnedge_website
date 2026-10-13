<?php

namespace App\Filament\Resources\MobileOtpChallenges\Tables;

use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Enums\SmsSendStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The status column and filter use the *effective* status: a pending challenge
 * past its expiry is shown and filtered as Expired, though the stored column
 * still says pending (see MobileOtpStatus).
 *
 * The number is searchable in full (support staff are handed a number by the
 * customer) but only ever displayed masked.
 */
class MobileOtpChallengesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mobile_number')
                    ->label('Mobile')
                    ->formatStateUsing(fn (MobileOtpChallenge $record): string => $record->maskedMobileNumber())
                    ->searchable(),
                TextColumn::make('purpose')
                    ->badge()
                    ->placeholder('Not recorded'),
                TextColumn::make('effective_status')
                    ->label('Status')
                    ->state(fn (MobileOtpChallenge $record): MobileOtpStatus => $record->effectiveStatus())
                    ->badge(),
                TextColumn::make('sms_status')
                    ->label('SMS')
                    ->badge(),
                TextColumn::make('sms_failure_reason')
                    ->label('Failure category')
                    ->placeholder('—'),
                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->state(fn (MobileOtpChallenge $record): string => "{$record->attempts} / {$record->max_attempts}")
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('verified_at')
                    ->label('Verified')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('sms_provider_reference')
                    ->label('Provider reference')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consumed_at')
                    ->label('Used by form')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(MobileOtpStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereEffectiveStatus(MobileOtpStatus::from($data['value']))
                        : $query),
                SelectFilter::make('purpose')
                    ->options(MobileOtpPurpose::class),
                SelectFilter::make('sms_status')
                    ->label('SMS status')
                    ->options(SmsSendStatus::class),
                Filter::make('created_at')
                    ->label('Requested date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)))
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
