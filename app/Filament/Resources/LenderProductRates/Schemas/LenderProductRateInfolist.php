<?php

namespace App\Filament\Resources\LenderProductRates\Schemas;

use App\Models\LenderProductRate;
use App\Support\Rates\RateFormat;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class LenderProductRateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Status')
                    ->columns(4)
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('display_status')
                            ->label('Status')
                            ->badge()
                            ->state(fn (LenderProductRate $record): string => $record->displayStatus())
                            ->color(fn (LenderProductRate $record): string => $record->displayStatusColor()),
                        TextEntry::make('verification_status')
                            ->label('Verification')
                            ->badge()
                            ->helperText(fn (LenderProductRate $record): ?string => $record->needsReverification() ? 'Needs (re-)verification before it can be published.' : null),
                        TextEntry::make('version')->prefix('v'),
                        TextEntry::make('review_note')
                            ->label('Reviewer\'s note')
                            ->placeholder('—')
                            ->color('warning'),
                    ]),

                Section::make('Lender offer')
                    ->columns(2)
                    ->components([
                        TextEntry::make('lenderProduct.loanProduct.name')->label('Loan type'),
                        TextEntry::make('lenderProduct.lender.name')->label('Bank / NBFC'),
                        TextEntry::make('variant')->label('Product variant')->placeholder('Standard product'),
                        TextEntry::make('borrower_segment')->label('Only for')->placeholder('All eligible borrowers'),
                    ]),

                Section::make('Interest rate')
                    ->columns(2)
                    ->components([
                        TextEntry::make('rate_range')
                            ->label('Published rate')
                            ->state(fn (LenderProductRate $record): string => RateFormat::range((float) $record->rate_min, $record->rate_max !== null ? (float) $record->rate_max : null, $record->rate_unit)),
                        TextEntry::make('rate_type')->placeholder('—'),
                        TextEntry::make('repayment_method')->placeholder('—'),
                        TextEntry::make('benchmark')->placeholder('—'),
                    ]),

                Section::make('Promotion and validity')
                    ->columns(2)
                    ->components([
                        TextEntry::make('promo_rate')
                            ->label('Promotional rate')
                            ->formatStateUsing(fn ($state, LenderProductRate $record): string => RateFormat::percent((float) $state)." {$record->rate_unit->suffix()}".($record->promo_label ? " — {$record->promo_label}" : ''))
                            ->placeholder('None'),
                        TextEntry::make('promo_ends_at')
                            ->label('Promotion ends')
                            ->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE)
                            ->placeholder('—'),
                        TextEntry::make('effective_from')->label('Valid from')->date('j M Y')->placeholder('On publication'),
                        TextEntry::make('effective_until')->label('Valid until')->date('j M Y')->placeholder('Until replaced'),
                    ]),

                Section::make('Evidence')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('source_type')
                            ->label('Source type')
                            ->badge(),
                        TextEntry::make('source_name')
                            ->label('Source name')
                            ->placeholder('—'),
                        TextEntry::make('source_url')
                            ->label('Source link')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->placeholder('No source recorded')
                            ->columnSpanFull(),
                        TextEntry::make('source_notes')
                            ->label('What the source says')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('last_verified_at')->label('Last checked on')->date('j M Y')->placeholder('Never'),
                        TextEntry::make('eligibility_notes')->label('Who gets this rate')->placeholder('—'),
                        TextEntry::make('disclaimer')->placeholder('—'),
                        IconEntry::make('is_featured')->label('Featured')->boolean(),
                    ]),

                Section::make('Audit trail')
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('creator.name')->label('Created by')->placeholder('System import'),
                        TextEntry::make('created_at')->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE),
                        TextEntry::make('editor.name')->label('Last edited by')->placeholder('—'),
                        TextEntry::make('submitter.name')->label('Submitted by')->placeholder('—'),
                        TextEntry::make('submitted_at')->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE)->placeholder('—'),
                        TextEntry::make('publisher.name')->label('Approved & published by')->placeholder('—'),
                        TextEntry::make('published_at')->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE)->placeholder('—'),
                        TextEntry::make('archived_at')->label('Archived')->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE)->placeholder('—'),
                    ]),

                Section::make('Preview')
                    ->description('How this version reads on the website.')
                    ->columnSpanFull()
                    ->components([
                        Text::make(fn (LenderProductRate $record) => RatePreview::render([
                            ...$record->only($record->getFillable()),
                            'last_verified_at' => $record->last_verified_at?->toDateString(),
                        ])),
                    ]),
            ]);
    }
}
