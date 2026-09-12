<?php

namespace App\Filament\Resources\ContactEnquiries\Tables;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\LoanProduct;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactEnquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('Not provided'),
                TextColumn::make('phone')
                    ->label('Mobile')
                    ->searchable(),
                TextColumn::make('loanProduct.name')
                    ->label('Loan product')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Lead source')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
                TextColumn::make('enquiry_source')
                    ->label('Enquiry source')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('enquiry_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('loan_amount')
                    ->label('Amount')
                    ->money('INR')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                // Repeat enquiries bump this instead of inserting a second row, so a
                // number above 1 is the signal that someone has asked more than once.
                TextColumn::make('enquiry_count')
                    ->label('Enquiries')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('handled_at')
                    ->label('Handled')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                /*
                 * Only products that have actually produced an enquiry are listed.
                 * The catalogue carries drafts and retired products; a filter full
                 * of options that can only ever return nothing is noise.
                 */
                SelectFilter::make('loan_product_id')
                    ->label('Loan product')
                    ->options(fn (): array => LoanProduct::query()
                        ->whereHas('enquiries')
                        ->orderedForDisplay()
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('source')
                    ->label('Lead source')
                    ->options(fn (): array => ['website' => 'Website']),
                SelectFilter::make('status')
                    ->options(EnquiryStatus::class)
                    ->multiple(),
                SelectFilter::make('enquiry_type')
                    ->label('Type')
                    ->options(EnquiryType::class),
                Filter::make('created_at')
                    ->label('Enquiry date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        // whereDate on the upper bound, not whereBetween on a datetime:
                        // "until 12 Sep" must include everything submitted that day.
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        return array_filter([
                            ($data['from'] ?? null) ? 'From '.$data['from'] : null,
                            ($data['until'] ?? null) ? 'Until '.$data['until'] : null,
                        ]);
                    }),
                TernaryFilter::make('handled_at')
                    ->label('Handled')
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
