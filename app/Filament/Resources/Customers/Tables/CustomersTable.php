<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Schemas\IndiaDateRangeFilter;
use App\Models\User;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomersCsv;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                TextColumn::make('portal_status')
                    ->label('Portal')
                    ->badge()
                    ->state(fn (Customer $record): string => CustomerForm::portalStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Disabled' => 'danger',
                        default => 'gray',
                    }),
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
                    ->label('First seen (IST)')
                    ->dateTime()
                    ->timezone(IndiaDateRangeFilter::TIMEZONE)
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('portal_account')
                    ->label('Portal account')
                    ->trueLabel('Activated')
                    ->falseLabel('Not activated')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('portal_activated_at'),
                        false: fn (Builder $query) => $query->whereNull('portal_activated_at'),
                    ),
                // Customers with any request at the chosen bureaus — only bureaus whose requests the user may list.
                SelectFilter::make('bureau')
                    ->label('Bureau')
                    ->multiple()
                    ->options(fn (): array => collect(self::visibleBureauRequests())
                        ->mapWithKeys(fn (array $bureau, string $key): array => [$key => BureauName::from($key)->getLabel()])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $chosen = array_intersect_key(self::visibleBureauRequests(), array_flip($data['values'] ?? []));

                        return $chosen === [] ? $query : $query->where(fn (Builder $query) => collect($chosen)
                            ->each(fn (array $bureau) => $query->orWhereHas($bureau[0])));
                    }),
                IndiaDateRangeFilter::make('created_at', 'First seen'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkAction::make('downloadSelected')
                    ->label('Download selected (CSV)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records): StreamedResponse => CustomersCsv::download(
                        Customer::query()->whereKey($records->modelKeys()),
                        ($user = auth()->user()) instanceof User ? $user : null,
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /**
     * Bureau key => [requests relation on Customer, request model], for the
     * bureaus whose requests the signed-in user may list (as the columns).
     *
     * @return array<string, array{0: string, 1: class-string<Model>}>
     */
    private static function visibleBureauRequests(): array
    {
        return array_filter([
            BureauName::Crif->value => ['crifReports', CrifReport::class],
            BureauName::Equifax->value => ['equifaxRequests', EquifaxCreditRequest::class],
            BureauName::Experian->value => ['experianRequests', ExperianCreditRequest::class],
            BureauName::Cibil->value => ['cibilRequests', CibilCreditRequest::class],
        ], fn (array $bureau): bool => (bool) auth()->user()?->can('viewAny', $bureau[1]));
    }
}
