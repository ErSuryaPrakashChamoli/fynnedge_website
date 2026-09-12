<?php

namespace App\Filament\Resources\ContactEnquiries\Tables;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContactEnquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')
                    ->label('Mobile number')
                    ->searchable(),
                TextColumn::make('enquiry_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('source')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('name')->searchable()->placeholder('—'),
                TextColumn::make('email')->searchable()->placeholder('—'),
                TextColumn::make('message')->limit(50)->placeholder('—'),
                // Repeat enquiries bump this instead of inserting a second row, so a
                // number above 1 is the signal that someone has asked more than once.
                TextColumn::make('enquiry_count')
                    ->label('Enquiries')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('handled_at')
                    ->label('Handled')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last activity')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('enquiry_type')
                    ->label('Type')
                    ->options(EnquiryType::class),
                SelectFilter::make('status')
                    ->options(EnquiryStatus::class),
                SelectFilter::make('source')
                    ->options(fn (): array => ['website' => 'Website', 'homepage' => 'Homepage']),
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
