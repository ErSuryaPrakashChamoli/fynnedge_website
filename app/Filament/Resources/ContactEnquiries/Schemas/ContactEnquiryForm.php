<?php

namespace App\Filament\Resources\ContactEnquiries\Schemas;

use App\Enums\EnquiryStatus;
use App\Support\Formatting\IndianNumberFormatter;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The enquiry detail view. Everything the visitor submitted, and everything the
 * server resolved about where it came from, is read-only — the only thing the
 * team changes here is the follow-up state. An editable "Loan product" or
 * "Enquiry source" would quietly rewrite the marketing numbers those columns
 * exist to produce.
 *
 * Read-only fields are TextEntry rather than disabled inputs: a disabled input
 * still round-trips through form state on save, an entry has no state to save.
 */
class ContactEnquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enquiry details')
                    ->columns(2)
                    ->components([
                        TextEntry::make('name')->label('Customer name')->placeholder('Not provided'),
                        TextEntry::make('phone')->label('Mobile')->placeholder('Not provided'),
                        TextEntry::make('email')->label('Email')->placeholder('Not provided'),
                        TextEntry::make('created_at')
                            ->label('Submitted on')
                            ->dateTime('j F Y, H:i'),
                        TextEntry::make('message')
                            ->label('Message')
                            ->placeholder('Not provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Source')
                    ->description('Resolved on the server when the form was submitted — never taken from the browser.')
                    ->columns(2)
                    ->components([
                        TextEntry::make('loanProduct.name')
                            ->label('Loan product')
                            ->badge()
                            ->placeholder('Not product specific'),
                        TextEntry::make('source')
                            ->label('Lead source')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
                        TextEntry::make('enquiry_source')->label('Enquiry source')->placeholder('—'),
                        TextEntry::make('enquiry_type')->label('Type')->badge(),
                        TextEntry::make('source_url')->label('Landing page')->placeholder('—'),
                        TextEntry::make('loan_amount')
                            ->label('Loan amount')
                            ->placeholder('Not stated')
                            ->formatStateUsing(fn (string $state): string => '₹'.IndianNumberFormatter::format((float) $state)),
                        TextEntry::make('enquiry_count')
                            ->label('Times enquired')
                            ->helperText('A repeat enquiry about the same product bumps this count instead of creating a second record.'),
                        TextEntry::make('phone_verified_at')
                            ->label('Mobile verified')
                            ->placeholder('Not verified')
                            ->dateTime('j M Y, H:i'),
                    ]),

                Section::make('Follow-up')
                    ->columns(2)
                    ->components([
                        Select::make('status')
                            ->options(EnquiryStatus::class)
                            ->required()
                            ->native(false)
                            ->helperText('Converted, Rejected and Closed stamp the handled time automatically.'),
                        DateTimePicker::make('handled_at')
                            ->label('Handled at')
                            ->helperText('Set this once the enquiry has been followed up.'),
                    ]),
            ]);
    }
}
